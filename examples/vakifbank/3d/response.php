<?php

require '_config.php';

require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new \Symfony\Component\HttpFoundation\RedirectResponse($baseUrl);
    exit();
}

$order = (array) json_decode($redis->lPop('order'));

$pos->prepare($order, \Mews\Pos\Gateways\AbstractGateway::TX_PAY);
$pos->payment();
$response = $pos->getResponse();
?>

<div class="result">
    <h3 class="text-center text-<?php echo $pos->isSuccess() ? 'success' : 'danger'; ?>">
        <?php echo $pos->isSuccess() ? 'Payment is successful!' : 'Payment is not successful'; ?>
    </h3>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Response:</dt>
        <dd class="col-sm-9"><?php echo $response->response ? $response->response : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Status:</dt>
        <dd class="col-sm-9"><?php echo $response->status; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Transaction:</dt>
        <dd class="col-sm-9"><?php echo $response->transaction; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Transaction Type:</dt>
        <dd class="col-sm-9"><?php echo $response->transaction_type; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Transaction Security:</dt>
        <dd class="col-sm-9"><?php echo $response->transaction_security; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Hash:</dt>
        <dd class="col-sm-9">
            <?php foreach ($response->hash as $key => $hash): ?>
            <?php echo $key . ': ' . $hash; ?>
            <?php endforeach; ?>
        </dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Order ID:</dt>
        <dd class="col-sm-9"><?php echo $response->id; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">AuthCode:</dt>
        <dd class="col-sm-9"><?php echo $response->auth_code ? $response->auth_code : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">HostRefNum:</dt>
        <dd class="col-sm-9"><?php echo $response->host_ref_num ? $response->host_ref_num : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">ProcReturnCode:</dt>
        <dd class="col-sm-9"><?php echo $response->code ? $response->code : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">mdStatus:</dt>
        <dd class="col-sm-9"><?php echo $response->md_status ? $response->md_status : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Error Code:</dt>
        <dd class="col-sm-9"><?php echo $response->error_code ? $response->error_code : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Error Message:</dt>
        <dd class="col-sm-9"><?php echo $response->error_message ? $response->error_message : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-3">Md Error Message:</dt>
        <dd class="col-sm-9"><?php echo $response->md_error_message ? $response->md_error_message : '-'; ?></dd>
    </dl>
    <hr>
    <dl class="row">
        <dt class="col-sm-12">All Data Dump:</dt>
        <dd class="col-sm-12">
            <pre><?php dump($response); ?></pre>
        </dd>
    </dl>
    <hr>
    <div class="text-right">
        <a href="index.php" class="btn btn-lg btn-info">&lt; Click to payment form</a>
    </div>
</div>

<?php require '../../template/_footer.php'; ?>
