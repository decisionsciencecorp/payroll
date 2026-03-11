<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ApiIntegrationTest extends TestCase
{
    private const BASE_URL = 'http://127.0.0.1:8765';
    private static $serverProcess;
    private static $apiKey;

    public static function setUpBeforeClass(): void
    {
        self::$apiKey = $GLOBALS['payroll_test_api_key'] ?? null;
        if (!self::$apiKey) {
            self::markTestSkipped('No test API key');
        }
        $projectRoot = dirname(__DIR__, 1);
        $cmd = 'php -S 127.0.0.1:8765 -t public';
        $pipes = [];
        self::$serverProcess = proc_open(
            $cmd,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            $projectRoot
        );
        if (!is_resource(self::$serverProcess)) {
            self::markTestSkipped('Could not start PHP server');
        }
        // Wait for server to be ready
        $attempts = 0;
        while ($attempts < 20) {
            $ctx = stream_context_create(['http' => ['timeout' => 1]]);
            if (@file_get_contents(self::BASE_URL . '/api/list-tax-brackets.php', false, $ctx) !== false) {
                break;
            }
            usleep(100000);
            $attempts++;
        }
        if ($attempts >= 20) {
            proc_terminate(self::$serverProcess);
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
        $url = self::BASE_URL . $path;
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
        $this->assertSame(6500.0, $r['body']['employee']['monthly_gross_salary'] ?? 0);

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
        $this->assertCount(1, $r['body']['payroll'] ?? []);
        $payrollId = $r['body']['payroll'][0]['id'];

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

    /** Admin Users page must load without fatal (formatDate from functions.php). */
    public function testAdminUsersPageLoadsWithAuth(): void
    {
        $url = self::BASE_URL . '/admin/login.php';
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => 'username=admin&password=admin',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ];
        $ctx = stream_context_create($opts);
        @file_get_contents($url, false, $ctx);
        $headers = $http_response_header ?? [];
        $cookie = '';
        foreach ($headers as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                $cookie = trim(substr($h, 11));
                break;
            }
        }
        $this->assertNotEmpty($cookie, 'Login should set session cookie');
        $usersUrl = self::BASE_URL . '/admin/users.php';
        $opts2 = [
            'http' => [
                'method' => 'GET',
                'header' => "Cookie: $cookie\r\n",
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ];
        $body = @file_get_contents($usersUrl, false, stream_context_create($opts2));
        $this->assertNotFalse($body);
        $this->assertStringContainsString('Admin users', $body);
        $this->assertStringNotContainsString('Call to undefined function', $body);
    }
}
