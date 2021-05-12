<?php

require '../../_main_config.php';

$path = '/kuveytturk/3d/';
$baseUrl = $hostUrl . $path;

$success_url = $fail_url = $baseUrl . 'response.php';

$account = \Mews\Pos\Factory\AccountFactory::createBoaPosAccount('kuveytturk', 'xxx', 'xxx', 'xxxxx', 'xxxxx', '3d', 'xxx');

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

try {
    $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account);
    $pos->setTestMode(true);
} catch (\Mews\Pos\Exceptions\BankNotFoundException $e) {
    dump($e->getCode(), $e->getMessage());
} catch (\Mews\Pos\Exceptions\BankClassNullException $e) {
    dump($e->getCode(), $e->getMessage());
}

$templateTitle = '3D Model Payment';
