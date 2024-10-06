<div>
<?php
    // dd($order);
    // 'id' => $order->id,
    // 'tracking_number' => $order->tracking_number,
    // 'total_amount' => $order->total_amount,
    // 'items' => $items,
    // TODO: Fix this
    ?>
    <div>
        <h3>Customer Details</h3>
        <p>Shipping address: <?php echo $order->shipping_address; ?></p>
        <p>Billing address: <?php echo $order->billing_address; ?></p>
        <p>Shipping method: <?php echo $order->shipping_method; ?></p>
        <p>Email: <?="vendor@gmail.com"?></p>
        <p>Phone: <?="+8801637765144"?></p>
    </div>
    <br>
    <br>
    <div>
        <h3>Items:</h3>
    </div>
<?php
    foreach ($orderContent['items'] as $key => $item) {
        echo '<div class="row">';
        echo '<div class="col-md-9">';
        echo '<h4>' . $item['name'] . '</h4>';
        echo '<p>Quantity: ' . $item['quantity'] . '</p>';
        echo '<p>Price: '. $currency . ' ' . $item['regular_price'] . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<hr>';
    }
?>
</div>
