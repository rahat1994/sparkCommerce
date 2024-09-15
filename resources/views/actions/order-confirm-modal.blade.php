<div>
<?php
    // 'id' => $order->id,
    // 'tracking_number' => $order->tracking_number,
    // 'total_amount' => $order->total_amount,
    // 'items' => $items,
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
