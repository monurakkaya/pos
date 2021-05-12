<?php

require '_config.php';

require '../../template/_header.php';

if ($request->getMethod() !== 'POST') {
    echo new \Symfony\Component\HttpFoundation\RedirectResponse($baseUrl);
    exit();
}

$order = [
    'id'            => $_POST['order_id'],
    'name'          => $_POST['name'],
    'amount'        => $_POST['amount'],
    'currency'      => $_POST['currency'],
    'success_url'   => $_POST['success_url'],
    'fail_url'      => $_POST['fail_url'],
    'lang'          => $_POST['lang'],
];

$redis->lPush('order', json_encode($order));

$card = new \Mews\Pos\Entity\Card\CreditCardBoaPos(
    $request->get('number'),
    $request->get('year'),
    $request->get('month'),
    $request->get('cvv'),
    $request->get('name'),
    $request->get('type')
);

$pos->prepare($order, $_POST['transaction'], $card);

echo $pos->get3DFormData();
?>

<span class="text-muted text-center">Redirecting...</span>

<?php require '../../template/_footer.php'; ?>
