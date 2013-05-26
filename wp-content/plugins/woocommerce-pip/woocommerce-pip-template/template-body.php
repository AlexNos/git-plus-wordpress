		<header>
          <a class="print" href="#" onclick="window.print()"><?php _e('Print', 'woocommerce-pip'); ?></a>
  		    <div style="float: left; width: 49%;">
  		      <?php echo woocommerce_pip_print_logo(); ?>
  		      <?php if ($action == 'print_invoice') { ?>
  		      <h3><?php _e('Invoice', 'woocommerce-pip'); ?> (<?php echo woocommerce_pip_invoice_number($_GET['post']); ?>)</h3>
  		      <?php } else { ?>
  		      <h3><?php _e('Packing list', 'woocommerce-pip'); ?></h3>
  		      <?php } ?>
  		      <h3><?php _e('Order', 'woocommerce-pip'); ?> <?php echo $order->get_order_number(); ?> &mdash; <time datetime="<?php echo date("Y/m/d", strtotime($order->order_date)); ?>"><?php echo date("Y/m/d", strtotime($order->order_date)); ?></time></h3>
  		    </div>
  		    <div style="float: right; width: 49%; text-align:right;">
  		      <?php echo woocommerce_pip_print_company_name(); ?>
  		      <?php echo woocommerce_pip_print_company_extra(); ?>
  		    </div>
  		    <div style="clear:both;"></div>

  	</header>
  	<section>
		<div class="article">
			<header>

      			<div style="float:left; width: 49%;">

      				<h3><?php _e('Billing address', 'woocommerce-pip'); ?></h3>

      				<p>
      					<?php echo $order->get_formatted_billing_address(); ?>
      				</p>
      				<?php if (get_post_meta($order->id, 'VAT Number', TRUE) && $action == 'print_invoice') : ?>
        				<p><strong><?php _e('VAT:', 'woocommerce-pip'); ?></strong> <?php echo get_post_meta($order->id, 'VAT Number', TRUE); ?></p>
        			<?php endif; ?>
      				<?php if ($order->billing_email) : ?>
        				<p><strong><?php _e('Email:', 'woocommerce-pip'); ?></strong> <?php echo $order->billing_email; ?></p>
        			<?php endif; ?>
        			<?php if ($order->billing_phone) : ?>
        				<p><strong><?php _e('Tel:', 'woocommerce-pip'); ?></strong> <?php echo $order->billing_phone; ?></p>
        			<?php endif; ?>

      			</div>

      			<div style="float:right; width: 49%;">

      				<h3><?php _e('Shipping address', 'woocommerce-pip'); ?></h3>

      				<p>
      					<?php echo $order->get_formatted_shipping_address(); ?>
      				</p>
      				<?php if (get_post_meta( $order_id, '_tracking_provider', true )) : ?>
        				<p><strong><?php _e('Tracking provider:', 'woocommerce-pip'); ?></strong> <?php echo get_post_meta( $order_id, '_tracking_provider', true ); ?></p>
        			<?php endif; ?>
        			<?php if (get_post_meta( $order_id, '_tracking_number', true )) : ?>
        				<p><strong><?php _e('Tracking number:', 'woocommerce-pip'); ?></strong> <?php echo get_post_meta( $order_id, '_tracking_number', true ); ?></p>
        			<?php endif; ?>

      			</div>

      			<div style="clear:both;"></div>
      			
      			<?php if ($order->customer_note) { ?>
    		    <div>
    		      <h3><?php _e('Order notes', 'woocommerce-pip'); ?></h3>
    		      <?php echo $order->customer_note; ?>
    		    </div>
    		    <?php } ?>
    		    
			</header>
			<div class="datagrid">
        <?php if ($action == 'print_invoice') { ?>
			<table>
				<thead>
					<tr>
					  <th scope="col" style="text-align:left; width: 15%;"><?php _e('SKU', 'woocommerce-pip'); ?></th>
						<th scope="col" style="text-align:left; width: 40%;"><?php _e('Product', 'woocommerce-pip'); ?></th>
						<th scope="col" style="text-align:left; width: 15%;"><?php _e('Quantity', 'woocommerce-pip'); ?></th>
						<th scope="col" style="text-align:left; width: 30%;"><?php _e('Price', 'woocommerce-pip'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
					  <th colspan="2" style="text-align:left; padding-top: 12px;">&nbsp;</th>
						<th scope="row" style="text-align:right; padding-top: 12px;"><?php _e('Subtotal:', 'woocommerce-pip'); ?></th>
						<td style="text-align:left; padding-top: 12px;"><?php echo $order->get_subtotal_to_display(); ?></td>
					</tr>
					<tr>
					  <th colspan="2" style="text-align:left; padding-top: 12px;">&nbsp;</th>
						<th scope="row" style="text-align:right;"><?php _e('Shipping:', 'woocommerce-pip'); ?></th>
						<td style="text-align:left;"><?php echo $order->get_shipping_to_display(); ?></td>
					</tr>
					<?php if ($order->cart_discount > 0) : ?><tr>
					  <th colspan="2" style="text-align:left; padding-top: 12px;">&nbsp;</th>
						<th scope="row" style="text-align:right;"><?php _e('Cart Discount:', 'woocommerce-pip'); ?></th>
						<td style="text-align:left;"><?php echo woocommerce_price($order->cart_discount); ?></td>
					</tr><?php endif; ?>
					<?php if ($order->order_discount > 0) : ?><tr>
					  <th colspan="2" style="text-align:left; padding-top: 12px;">&nbsp;</th>
						<th scope="row" style="text-align:right;"><?php _e('Order Discount:', 'woocommerce-pip'); ?></th>
						<td style="text-align:left;"><?php echo woocommerce_price($order->order_discount); ?></td>
					</tr><?php endif; ?>
          <tr>
            <th colspan="2" style="text-align:left; padding-top: 12px;">&nbsp;</th>
						<th scope="row" style="text-align:right;"><?php _e('Tax:', 'woocommerce-pip'); ?></th>
						<td style="text-align:left;"><?php echo woocommerce_price($order->get_total_tax()); ?></td>
					</tr>
					<tr>
					  <th colspan="2" style="text-align:left; padding-top: 12px;">&nbsp;</th>
						<th scope="row" style="text-align:right;"><?php _e('Total:', 'woocommerce-pip'); ?></th>
						<td style="text-align:left;"><?php echo woocommerce_price($order->order_total); ?> <?php _e('- via', 'woocommerce-pip'); ?> <?php echo ucwords($order->payment_method_title); ?></td>
					</tr>
				</tfoot>
				<tbody>
					<?php echo woocommerce_pip_order_items_table($order, TRUE); ?>
				</tbody>
			</table>
			<?php }
			else {
  			global $woocommerce; ?>
			<table>
				<thead>
					<tr>
					  <th scope="col" style="text-align:left; width: 25%;"><?php _e('SKU', 'woocommerce-pip'); ?></th>
						<th scope="col" style="text-align:left; width: 60%;"><?php _e('Product', 'woocommerce-pip'); ?></th>
						<th scope="col" style="text-align:left; width: 15%;"><?php _e('Quantity', 'woocommerce-pip'); ?></th>
						<th scope="col" style="text-align:left; width: 15%;"><?php _e('Weight', 'woocommerce-pip'); ?></th>
					</tr>
				</thead>
				<tfoot>
				  <tr>
				    <th colspan="2" style="text-align:left; padding-top: 12px;">&nbsp;</th>
				    <th scope="row" style="text-align:right; padding-top: 12px;"><?php _e('Total weight:', 'woocommerce-pip'); ?></th>
				    <td style="text-align:left; padding-top: 12px;"><?php echo $woocommerce->cart->cart_contents_weight; ?></td>
				  </tr>
				</tfoot>
				<tbody>
					<?php echo woocommerce_pip_order_items_table($order); ?>
				</tbody>
			</table>
			<?php } ?>
			</div>
		</div>
	  <div class="article">
	    <?php echo woocommerce_pip_print_return_policy(); ?>
	  </div>
	</section>
	<div class="footer">
	  <?php echo woocommerce_pip_print_footer(); ?>
	</div>
  <p class="pagebreak"></p>
