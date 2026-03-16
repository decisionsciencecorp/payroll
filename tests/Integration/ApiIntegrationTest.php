<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ApiIntegrationTest extends TestCase
{
    private const BASE_URL = 'http://127.0.0.1:9876';
    private static $serverProcess;
    private static $apiKey;

    public static function setUpBeforeClass(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $dbDir = $projectRoot . '/db';
        $keyFile = $projectRoot . '/tests/_server_api_key.txt';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        $phpBinary = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';
        $fun = $projectRoot . '/public/includes/functions.php';
        $code = 'require_once ' . var_export($fun, true) . '; initializeDatabase(); file_put_contents(' . var_export($keyFile, true) . ', createApiKey("phpunit"));';
        $provEnv = getenv();
        if (is_array($provEnv)) {
            unset($provEnv['PAYROLL_TEST'], $provEnv['DB_PATH'], $provEnv['STORAGE_PATH']);
        } else {
            $provEnv = [];
        }
        $provProc = proc_open(
            $phpBinary . ' -r ' . escapeshellarg($code),
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $provPipes,
            $projectRoot,
            $provEnv
        );
        if (is_resource($provProc)) {
            foreach ($provPipes as $p) {
                if (is_resource($p)) {
                    fclose($p);
                }
            }
            proc_close($provProc);
        }
        if (!file_exists($keyFile)) {
            self::markTestSkipped('Could not provision API key in server DB');
        }
        self::$apiKey = trim(file_get_contents($keyFile));
        if (self::$apiKey === '') {
            self::markTestSkipped('No test API key');
        }
        $env = getenv();
        if (is_array($env)) {
            unset($env['PAYROLL_TEST'], $env['DB_PATH'], $env['STORAGE_PATH']);
        } else {
            $env = [];
        }
        $cmd = $phpBinary . ' -S 127.0.0.1:9876 -t public';
        $pipes = [];
        @exec('fuser -k 9876/tcp 2>/dev/null || true');
        usleep(100000);
        self::$serverProcess = proc_open(
            $cmd,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            $projectRoot,
            $env
        );
        if (!is_resource(self::$serverProcess)) {
            self::markTestSkipped('Could not start PHP server');
        }
        // Close pipes so the server does not block on stdout/stderr
        foreach ($pipes as $p) {
            if (is_resource($p)) {
                fclose($p);
            }
        }
        // Wait for server to be ready (up to 5 seconds). 401 = no API key is a valid response.
        $attempts = 0;
        while ($attempts < 50) {
            $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
            if (@file_get_contents(self::BASE_URL . '/api/list-tax-brackets.php', false, $ctx) !== false) {
                break;
            }
            usleep(100000);
            $attempts++;
        }
        if ($attempts >= 50) {
            proc_terminate(self::$serverProcess);
            @proc_close(self::$serverProcess);
            self::markTestSkipped('Server did not become ready');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    private function request(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        $sep = strpos($path, '?') !== false ? '&' : '?';
        $url = self::BASE_URL . $path . $sep . 'api_key=' . rawurlencode(self::$apiKey);
        $headerLines = ['X-API-Key: ' . self::$apiKey];
        foreach ($headers as $k => $v) {
            $headerLines[] = "$k: $v";
        }
        if ($body !== null && !isset($headers['Content-Type'])) {
            $headerLines[] = 'Content-Type: application/json';
        }
        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ];
        if ($body !== null) {
            $opts['http']['content'] = $body;
        }
        $ctx = stream_context_create($opts);
        $response = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/ (\d+) /', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        $json = $response ? json_decode($response, true) : null;
        return ['code' => $code, 'body' => $json !== null ? $json : $response];
    }

    /** Log in as admin (default seed user). Returns session cookie string or empty on failure. */
    private function loginAsAdmin(): string
    {
        $loginUrl = self::BASE_URL . '/admin/login.php';
        $loginPage = @file_get_contents($loginUrl, false, stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true],
        ]));
        if ($loginPage === false) {
            return '';
        }
        $cookie = '';
        foreach ($http_response_header ?? [] as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                $cookie = trim(substr($h, 11));
                break;
            }
        }
        if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $m)) {
            return '';
        }
        @file_get_contents($loginUrl, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
                'content' => http_build_query([
                    'username' => 'admin',
                    'password' => 'admin',
                    'csrf_token' => $m[1],
                ]),
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]));
        foreach ($http_response_header ?? [] as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                return trim(substr($h, 11));
            }
        }
        return '';
    }

    /** GET a path with session cookie; returns ['code' => int, 'body' => string]. */
    private function getWithCookie(string $path, string $cookie): array
    {
        $url = self::BASE_URL . $path;
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "Cookie: $cookie\r\n",
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ];
        $body = @file_get_contents($url, false, stream_context_create($opts));
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/ (\d+) /', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        return ['code' => $code, 'body' => $body !== false ? $body : ''];
    }

    /** POST multipart/form-data with file (name="logo") to upload-logo.php; returns code + body. */
    private function requestUploadLogo(string $imageContent, string $filename, string $mimeType): array
    {
        $boundary = '----PayrollTest' . bin2hex(random_bytes(8));
        $crlf = "\r\n";
        $body = '--' . $boundary . $crlf
            . 'Content-Disposition: form-data; name="logo"; filename="' . $filename . '"' . $crlf
            . 'Content-Type: ' . $mimeType . $crlf . $crlf
            . $imageContent . $crlf
            . '--' . $boundary . '--' . $crlf;
        $headers = [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            'Content-Length' => (string) strlen($body),
        ];
        $url = self::BASE_URL . '/api/upload-logo.php';
        $headerLines = ['X-API-Key: ' . self::$apiKey];
        foreach ($headers as $k => $v) {
            $headerLines[] = "$k: $v";
        }
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ];
        $ctx = stream_context_create($opts);
        $response = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/ (\d+) /', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        $json = $response ? json_decode($response, true) : null;
        return ['code' => $code, 'body' => $json !== null ? $json : $response];
    }

    public function testUploadLogoSuccess(): void
    {
        // Minimal valid 1x1 PNG (68 bytes)
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKMIQQAAAABJRU5ErkJggg==');
        $this->assertNotEmpty($png, 'Decode test PNG');
        $r = $this->requestUploadLogo($png, 'logo.png', 'image/png');
        $this->assertSame(200, $r['code'], 'Upload logo should return 200: ' . json_encode($r['body'] ?? $r));
        $this->assertIsArray($r['body']);
        $this->assertTrue($r['body']['success'] ?? false);
        $r2 = $this->request('GET', '/api/logo-file.php');
        $this->assertSame(200, $r2['code'], 'After upload, logo-file.php should return 200');
    }

    public function testTaxBracketsUploadListGetDelete(): void
    {
        $config = [
            'year' => 2028,
            'ss_wage_base' => 184500,
            'fica_ss_rate' => 0.062,
            'fica_medicare_rate' => 0.0145,
            'additional_medicare_rate' => 0.009,
            'additional_medicare_thresholds' => [
                'single' => 200000,
                'married_filing_jointly' => 250000,
                'married_filing_separately' => 125000,
            ],
            'brackets' => [
                'single' => [['min' => 0, 'max' => 10000, 'rate' => 0.10]],
                'married' => [['min' => 0, 'max' => 20000, 'rate' => 0.10]],
                'head_of_household' => [['min' => 0, 'max' => 15000, 'rate' => 0.10]],
            ],
        ];
        $r = $this->request('POST', '/api/upload-tax-brackets.php', json_encode($config));
        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success'] ?? false);

        $r = $this->request('GET', '/api/list-tax-brackets.php');
        $this->assertSame(200, $r['code']);
        $this->assertContains(2028, $r['body']['years'] ?? []);

        $r = $this->request('GET', '/api/get-tax-brackets.php?year=2028');
        $this->assertSame(200, $r['code']);
        $this->assertSame(2028, $r['body']['year'] ?? 0);
        $this->assertArrayHasKey('config', $r['body']);

        $r = $this->request('DELETE', '/api/delete-tax-brackets.php?year=2028');
        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success'] ?? false);
    }

    public function testCreateListGetUpdateDeleteEmployee(): void
    {
        $create = [
            'full_name' => 'Test Employee',
            'ssn' => '987-65-4321',
            'filing_status' => 'Single',
            'hire_date' => '2026-01-15',
            'monthly_gross_salary' => 6000,
        ];
        $r = $this->request('POST', '/api/create-employee.php', json_encode($create));
        $this->assertSame(201, $r['code']);
        $this->assertArrayHasKey('employee', $r['body']);
        $id = $r['body']['employee']['id'];

        $r = $this->request('GET', '/api/list-employees.php');
        $this->assertSame(200, $r['code']);
        $this->assertNotEmpty($r['body']['employees']);
        $this->assertStringContainsString('4321', $r['body']['employees'][0]['ssn'] ?? '');
        $this->assertStringNotContainsString('987', $r['body']['employees'][0]['ssn'] ?? '');

        $r = $this->request('GET', "/api/get-employee.php?id=$id");
        $this->assertSame(200, $r['code']);
        $this->assertSame('987654321', $r['body']['employee']['ssn'] ?? '');

        $r = $this->request('POST', '/api/update-employee.php', json_encode(['id' => $id, 'monthly_gross_salary' => 6500]));
        $this->assertSame(200, $r['code']);
        $this->assertEquals(6500.0, $r['body']['employee']['monthly_gross_salary'] ?? 0);

        $r = $this->request('DELETE', "/api/delete-employee.php?id=$id");
        $this->assertSame(200, $r['code']);
    }

    public function testRunPayrollListGetStub(): void
    {
        $config = [
            'year' => 2026,
            'ss_wage_base' => 184500,
            'fica_ss_rate' => 0.062,
            'fica_medicare_rate' => 0.0145,
            'additional_medicare_rate' => 0.009,
            'additional_medicare_thresholds' => [
                'single' => 200000,
                'married_filing_jointly' => 250000,
                'married_filing_separately' => 125000,
            ],
            'brackets' => [
                'single' => [['min' => 0, 'max' => 100000, 'rate' => 0.10]],
                'married' => [['min' => 0, 'max' => 100000, 'rate' => 0.10]],
                'head_of_household' => [['min' => 0, 'max' => 100000, 'rate' => 0.10]],
            ],
        ];
        $this->request('POST', '/api/upload-tax-brackets.php', json_encode($config));

        $create = [
            'full_name' => 'Payroll Test User',
            'ssn' => '111-22-3333',
            'filing_status' => 'Single',
            'hire_date' => '2026-01-01',
            'monthly_gross_salary' => 5000,
        ];
        $r = $this->request('POST', '/api/create-employee.php', json_encode($create));
        $this->assertSame(201, $r['code']);
        $empId = $r['body']['employee']['id'];

        $run = [
            'pay_period_start' => '2026-01-01',
            'pay_period_end' => '2026-01-31',
            'pay_date' => '2026-01-31',
            'employee_ids' => [$empId],
        ];
        $r = $this->request('POST', '/api/run-payroll.php', json_encode($run));
        $this->assertSame(200, $r['code']);
        $this->assertSame(1, $r['body']['records'] ?? 0);

        $r = $this->request('GET', '/api/list-payroll.php?pay_date_from=2026-01-01&pay_date_to=2026-01-31');
        $this->assertSame(200, $r['code']);
        $payrolls = $r['body']['payroll'] ?? [];
        $this->assertGreaterThanOrEqual(1, count($payrolls));
        $our = null;
        foreach ($payrolls as $p) {
            if (($p['pay_date'] ?? '') === '2026-01-31') {
                $our = $p;
                break;
            }
        }
        $this->assertNotNull($our, 'Expected at least one payroll for 2026-01-31');
        $payrollId = $our['id'];

        $r = $this->request('GET', "/api/get-payroll.php?id=$payrollId");
        $this->assertSame(200, $r['code']);
        $this->assertArrayHasKey('payroll', $r['body']);

        $stubResp = @file_get_contents(
            self::BASE_URL . "/api/pdf-stub.php?id=$payrollId",
            false,
            stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'X-API-Key: ' . self::$apiKey,
                    'timeout' => 3,
                    'ignore_errors' => true,
                ],
            ])
        );
        $this->assertNotFalse($stubResp);
        $this->assertStringContainsString('Pay stub', $stubResp);
    }

    public function testInvalidApiKeyReturns401(): void
    {
        $url = self::BASE_URL . '/api/list-tax-brackets.php';
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'X-API-Key: invalid-key',
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ];
        $response = @file_get_contents($url, false, stream_context_create($opts));
        $this->assertNotEmpty($response);
        $body = json_decode($response, true);
        $this->assertIsArray($body);
        $this->assertFalse($body['success'] ?? true);
    }

    /** @see Issue #1: logo-file.php must require API key or admin session */
    public function testLogoFileRequiresAuth(): void
    {
        $url = self::BASE_URL . '/api/logo-file.php';
        $optsNoKey = [
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ];
        $ctx = stream_context_create($optsNoKey);
        $response = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/ (\d+) /', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        $this->assertSame(401, $code, 'logo-file.php must return 401 when no API key or session');
        $body = $response ? json_decode($response, true) : null;
        $this->assertIsArray($body);
        $this->assertArrayHasKey('error', $body);

        $r = $this->request('GET', '/api/logo-file.php');
        $this->assertContains($r['code'], [200, 404], 'With valid API key: 200 if logo exists, 404 if not');
        if ($r['code'] === 404) {
            $this->assertNull($r['body'] ?? null);
        }
    }

    public function testWrongMethodReturns405(): void
    {
        $r = $this->request('POST', '/api/list-tax-brackets.php');
        $this->assertSame(405, $r['code']);
    }

    public function testGetTaxBracketsMissingYear(): void
    {
        $r = $this->request('GET', '/api/get-tax-brackets.php');
        $this->assertSame(400, $r['code']);
    }

    public function testGetTaxBracketsNotFound(): void
    {
        $r = $this->request('GET', '/api/get-tax-brackets.php?year=1999');
        $this->assertSame(404, $r['code']);
    }

    public function testDeleteEmployeeWithPayrollReturns409(): void
    {
        $config = [
            'year' => 2027,
            'ss_wage_base' => 184500,
            'fica_ss_rate' => 0.062,
            'fica_medicare_rate' => 0.0145,
            'additional_medicare_rate' => 0.009,
            'additional_medicare_thresholds' => [
                'single' => 200000,
                'married_filing_jointly' => 250000,
                'married_filing_separately' => 125000,
            ],
            'brackets' => [
                'single' => [['min' => 0, 'max' => 100000, 'rate' => 0.10]],
                'married' => [['min' => 0, 'max' => 100000, 'rate' => 0.10]],
                'head_of_household' => [['min' => 0, 'max' => 100000, 'rate' => 0.10]],
            ],
        ];
        $this->request('POST', '/api/upload-tax-brackets.php', json_encode($config));
        $r = $this->request('POST', '/api/create-employee.php', json_encode([
            'full_name' => 'Conflict Test',
            'ssn' => '555-66-7777',
            'filing_status' => 'Single',
            'hire_date' => '2027-01-01',
            'monthly_gross_salary' => 4000,
        ]));
        $this->assertSame(201, $r['code']);
        $id = $r['body']['employee']['id'];
        $this->request('POST', '/api/run-payroll.php', json_encode([
            'pay_period_start' => '2027-01-01',
            'pay_period_end' => '2027-01-31',
            'pay_date' => '2027-01-31',
            'employee_ids' => [$id],
        ]));
        $r = $this->request('DELETE', "/api/delete-employee.php?id=$id");
        $this->assertSame(409, $r['code']);
        $this->assertStringContainsString('payroll history', $r['body']['error'] ?? '');
    }

    public function testUploadTaxBracketsValidation(): void
    {
        $r = $this->request('POST', '/api/upload-tax-brackets.php', json_encode(['year' => 2026]));
        $this->assertSame(400, $r['code']);
        $r = $this->request('POST', '/api/upload-tax-brackets.php', json_encode([]));
        $this->assertSame(400, $r['code']);
    }

    public function testRunPayrollMissingDates(): void
    {
        $r = $this->request('POST', '/api/run-payroll.php', json_encode([]));
        $this->assertSame(400, $r['code']);
    }

    public function testRunPayrollNoTaxConfigForYear(): void
    {
        $r = $this->request('POST', '/api/run-payroll.php', json_encode([
            'pay_period_start' => '2030-01-01',
            'pay_period_end' => '2030-01-31',
            'pay_date' => '2030-01-31',
        ]));
        $this->assertSame(400, $r['code']);
        $this->assertStringContainsString('No tax config', $r['body']['error'] ?? '');
    }

    public function testGetEmployeeMissingId(): void
    {
        $r = $this->request('GET', '/api/get-employee.php');
        $this->assertSame(400, $r['code']);
    }

    public function testGetPayrollNotFound(): void
    {
        $r = $this->request('GET', '/api/get-payroll.php?id=999999');
        $this->assertSame(404, $r['code']);
    }

    /** Admin Users page must load without fatal (formatDate from functions.php). Uses CSRF token for login (fixes #2). */
    public function testAdminUsersPageLoadsWithAuth(): void
    {
        $cookie = $this->loginAsAdmin();
        $this->assertNotEmpty($cookie, 'Login should set session cookie');
        $r = $this->getWithCookie('/admin/users.php', $cookie);
        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString('Admin users', $r['body']);
        $this->assertStringNotContainsString('Call to undefined function', $r['body']);
    }

    public function testGenerateW2MissingYear(): void
    {
        $r = $this->request('GET', '/api/generate-w2.php');
        $this->assertGreaterThanOrEqual(400, $r['code'], 'Missing year should yield 4xx or 5xx');
    }

    public function testGenerateW2RequiresEmployer(): void
    {
        $r = $this->request('GET', '/api/generate-w2.php?year=2026');
        $this->assertGreaterThanOrEqual(400, $r['code'], 'Missing employer should yield 4xx or 5xx');
        if (isset($r['body']['error'])) {
            $this->assertStringContainsString('Employer', $r['body']['error']);
        }
    }

    public function testDeleteTaxBracketsMissingYear(): void
    {
        $r = $this->request('DELETE', '/api/delete-tax-brackets.php');
        $this->assertSame(400, $r['code']);
    }

    public function testDeleteTaxBracketsNotFound(): void
    {
        $r = $this->request('DELETE', '/api/delete-tax-brackets.php?year=1999');
        $this->assertSame(404, $r['code']);
    }

    public function testListPayrollWithFilters(): void
    {
        $r = $this->request('GET', '/api/list-payroll.php?limit=5&offset=0');
        $this->assertSame(200, $r['code']);
        $this->assertArrayHasKey('payroll', $r['body']);
        $this->assertArrayHasKey('count', $r['body']);
    }

    public function testCreateEmployeeValidationBadSsn(): void
    {
        $r = $this->request('POST', '/api/create-employee.php', json_encode([
            'full_name' => 'Bad SSN',
            'ssn' => '123',
            'filing_status' => 'Single',
            'hire_date' => '2026-01-01',
            'monthly_gross_salary' => 5000,
        ]));
        $this->assertSame(400, $r['code']);
    }

    public function testCreateEmployeeValidationBadDate(): void
    {
        $r = $this->request('POST', '/api/create-employee.php', json_encode([
            'full_name' => 'Bad Date',
            'ssn' => '123-45-6789',
            'filing_status' => 'Single',
            'hire_date' => 'not-a-date',
            'monthly_gross_salary' => 5000,
        ]));
        $this->assertSame(400, $r['code']);
    }

    public function testPdfStubNotFound(): void
    {
        $r = $this->request('GET', '/api/pdf-stub.php?id=999999');
        $this->assertSame(404, $r['code']);
    }

    /** E2E: After login, all admin pages return 200 and no fatal. */
    public function testAllAdminPagesLoadAfterLogin(): void
    {
        $cookie = $this->loginAsAdmin();
        $this->assertNotEmpty($cookie);
        $pages = [
            '/admin/index.php',
            '/admin/employees.php',
            '/admin/payroll.php',
            '/admin/tax-config.php',
            '/admin/api-keys.php',
            '/admin/logo.php',
            '/admin/company-settings.php',
            '/admin/w2.php',
            '/admin/users.php',
            '/admin/change-password.php',
        ];
        foreach ($pages as $path) {
            $r = $this->getWithCookie($path, $cookie);
            $this->assertSame(200, $r['code'], "Page $path should return 200");
            $this->assertStringNotContainsString('Call to undefined function', $r['body'] ?? '');
        }
    }
}
