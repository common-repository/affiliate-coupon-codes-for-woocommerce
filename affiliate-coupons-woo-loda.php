<?php
/**
 * Plugin Name: Affiliate Coupon Codes for Woocommerce
 * Description: This plugin allows you to add affiliates to Woocommerce coupon codes and generate a report for commissions and payouts.
 * Version:     1.0
 * Author:      John Loda
 */

// Get totals orders sum for a coupon code
function loda_get_total_sales_by_coupon( $coupon_code ) {
    global $wpdb;

    $coutotals = (float) $wpdb->get_var( $wpdb->prepare("
        SELECT SUM( pm.meta_value )
        FROM {$wpdb->prefix}postmeta pm
        INNER JOIN {$wpdb->prefix}posts p
            ON pm.post_id = p.ID
        INNER JOIN {$wpdb->prefix}woocommerce_order_items woi
            ON woi.order_id = pm.post_id
        WHERE (pm.meta_key = '_order_total')
        AND p.post_status IN ('wc-completed')
        AND woi.order_item_name LIKE '%s'
        AND woi.order_item_type = 'coupon'
    ", $coupon_code ) );
    
    $coutaxship = (float) $wpdb->get_var( $wpdb->prepare("
        SELECT SUM( pm.meta_value )
        FROM {$wpdb->prefix}postmeta pm
        INNER JOIN {$wpdb->prefix}posts p
            ON pm.post_id = p.ID
        INNER JOIN {$wpdb->prefix}woocommerce_order_items woi
            ON woi.order_id = pm.post_id
        WHERE (pm.meta_key = '_order_tax' OR pm.meta_key = '_order_shipping')
        AND p.post_status IN ('wc-completed')
        AND woi.order_item_name LIKE '%s'
        AND woi.order_item_type = 'coupon'
    ", $coupon_code ) );
    
    return $coutotals - $coutaxship;
}

// Adding Meta container to admin shop_coupon pages
add_action( 'add_meta_boxes', 'loda_add_custom_coupon_meta_box' );
if ( ! function_exists( 'loda_add_custom_coupon_meta_box' ) )
{
    function loda_add_custom_coupon_meta_box()
    {
        add_meta_box( 'coupon_usage_data', __('Affiliate','woocommerce'), 'loda_custom_coupon_meta_box_content', 'shop_coupon', 'side', 'core' );
    }
}

//when coupon post is updated, update the Affiliate
add_action( 'woocommerce_update_coupon', 'loda_coupon_update' );  
function loda_coupon_update() {
    global $post;
    
        if(($_POST['authorCC']) != null) {  
            $selected = sanitize_text_field($_POST['authorCC']);  
        update_post_meta($post->ID, '_coupon_affiliate_ID', $selected);
        update_post_meta($post->ID, '_coupon_affiliate_status', "1");
        }
        
        if(($_POST['authorCC']) == "-") {  
        update_post_meta($post->ID, '_coupon_affiliate_status', "0");
        }
        

}

// Displaying content in the meta container on admin shop_coupon pages
if ( ! function_exists( 'loda_custom_coupon_meta_box_content' ) )
{
    function loda_custom_coupon_meta_box_content() {
        global $post;
        $bloguseres = [];
        $blogusers = get_users(array(
    'meta_key' => '_CCAFF_vendor_status',
    'meta_value' => '1'
));
        $ccAffiliateID = get_post_meta($post->ID, '_coupon_affiliate_ID');
        $ccAffiliateData = get_user_by( 'ID', $ccAffiliateID[0] );
        ?>
        
        
<script>
/* Filters the useres search box*/

function lodaFilterFunction() {
  var input, filter, ul, li, a, i;
  input = document.getElementById("myInputz69");
  filter = input.value.toUpperCase();
  div = document.getElementById("authorCC");
  a = div.getElementsByTagName("option");
  for (i = 0; i < a.length; i++) {
    txtValue = a[i].textContent || a[i].innerText;
    if (txtValue.toUpperCase().indexOf(filter) > -1) {
      a[i].style.display = "";
    } else {
      a[i].style.display = "none";
    }
  }
}
</script>
<style>
.affselect {
	min-height: 100px;
	width: 100%;
}
</style>
        
        <div>Affiliate:<br> <b><?php if($ccAffiliateID[0] != "-" && $ccAffiliateID[0] != null) {echo '<span>' . esc_html( $ccAffiliateData->user_login) .' (#' . esc_html( $ccAffiliateData->ID) . ' - ' .esc_html( $ccAffiliateData->user_email) . ')</span>'; }?></b></div><br>

        <div id="CCsearchContain">
        <span>Assign Affiliate:</span><br>    
        <input type="text" class="affsearch" placeholder="Search.." id="myInputz69" onkeyup="lodaFilterFunction()"><br>
        <select class="affselect" name="authorCC" id="authorCC" size="5" onchange="ChangedSelection()">
        <option value="-">-</option>
        
        <?php
        foreach ( $blogusers as $user ) {
         echo '<option value="' . esc_html( $user->ID) . '">' . esc_html( $user->user_login) .' (#' . esc_html( $user->ID) . ' - ' .esc_html( $user->user_email) . ')</option>';
} 
        ?>
        </select></div> <br>
        <?php
        
        // if(!empty($_POST['authorCC'])) {  
        //     $selected = $_POST['authorCC'];  
        //     echo 'You have chosen: ' . $selected;  
        // }
        


        $total = loda_get_total_sales_by_coupon( $post->post_title );

        printf( __("Total sales: %s"), wc_price( $total ) );
        
    //    wp_dropdown_users( array( "name" => "author" ));

    }
}




 function loda_affiliate_coupons_admin_menu() {
	 add_menu_page('Affiliate Coupons','Affiliate Coupons','manage_options','affiliate-coupons-admin-menu','loda_affiliate_coupons_menu_main','dashicons-money',6);
	 add_submenu_page('affiliate-coupons-admin-menu', 'Affiliates', 'Affiliates', 'manage_options', 'affiliate-coupons-affiliates', 'loda_affiliate_coupons_affiliates');
	 add_submenu_page('affiliate-coupons-admin-menu', 'Commissions', 'Commissions', 'manage_options', 'affiliate-coupons-commissions', 'loda_affiliate_coupons_commissions');
 }
 add_action('admin_menu','loda_affiliate_coupons_admin_menu');
 
 
 //main affiliate coupons page admin menu
 function loda_affiliate_coupons_menu_main() {
	 
    $coupon_posts = get_posts( array(
        'posts_per_page'   => -1,
        'orderby'          => 'date',
        'order'            => 'desc',
        'post_type'        => 'shop_coupon',
        'meta_key'         => '_coupon_affiliate_status',
        'meta_value'       => '1'
    ) );

    $coupon_codes = []; // Initializing
    
    ?> 

<div class="wrap">
<H2>Affiliate Coupons</H2>

<div style="float: right; width: 250px; margin-right:50px; text-align:center;">
<span>If you are enjoying this plugin, please consider making a donation to help me keep making plugins like this one :)<span><br><br>
<form action="https://www.paypal.com/donate" method="post" target="_top">
<input type="hidden" name="hosted_button_id" value="4JUF828CBM9B2" />
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
</form>
</div>

<h3>Instructions on how to use Affiliate Coupons</h3>
<span>1. Assign an affiliate by going to the "Affiliates" tab. Make sure the user you want to be an affiliate is registed on your site through the standard WordPress Users.</span><br>
<span>2. After you have created an affiliate, go to your woocommerce coupons page and either create a new coupon for the affiliate or edit an existing one.<span><br>
<span>3. On the edit coupon page on the right hand side you will see a box titled "Affiliate" you can then search and select an Affiliate to be assigned to this coupon. After selecting a coupon, update the coupon. <span><br>
<span>4. Simply give your affiliate their coupon code assigned to them and let the orders roll in :)<span><br>
<span>5. After an order has been placed that uses an affiliate coupon and once it is set to the "completed" status you can go to the "Commissions" page where you will see a list of outstanding commissions you need to pay. <span><br>
<span>6. After you've paid out all of your affilaites, click the "mark All As Paid" button to clear all the outstanding commissions.</span><br><br>
<span> Enjoy! <span>
</div>

    <?php

 }

function loda_affiliate_coupons_affiliates(){
    $bloguseres = [];
    $blogusers = get_users();
    
    if (array_key_exists('submint_Affiliate_CCAff', $_POST)){
		$aCCaddID = sanitize_text_field($_POST['authorCCAdd']);
        update_user_meta( $aCCaddID, '_CCAFF_vendor_status', '1' );
        update_user_meta( $aCCaddID, '_CCAFF_vendor_comm_pct', sanitize_text_field($_POST['addAffpercent']) );
        update_user_meta( $aCCaddID, '_CCAFF_vendor_PPemail', sanitize_email($_POST['addAffPPemail']));
        update_user_meta( $aCCaddID, '_CCAFF_vendor_notes', sanitize_textarea_field($_POST['addAffNotes'] ));
    }
    
    if(isset($_POST['deleteItemAFF']) and is_numeric($_POST['deleteItemAFF']))
    {
     update_user_meta( sanitize_text_field($_POST['deleteItemAFF']), '_CCAFF_vendor_status', '0' );
     $theIDtodel = sanitize_text_field($_POST['deleteItemAFF']);
     
     $coupon_posts = get_posts( array(
        'posts_per_page'   => -1,
        'orderby'          => 'date',
        'order'            => 'desc',
        'post_type'        => 'shop_coupon',
        'meta_key'         => '_coupon_affiliate_ID',
        'meta_value'       => $theIDtodel
    ) );
    
    foreach( $coupon_posts as $coupon_post) {
        $ccname = $coupon_post->ID;
        update_post_meta($ccname, '_coupon_affiliate_status', "0");
        update_post_meta($ccname, '_coupon_affiliate_ID', "-");
    }
    
    }
    
    if(isset($_POST['updateAFFRow']) and is_numeric($_POST['updateAFFRow']))
    {
        update_user_meta( sanitize_text_field($_POST['updateAFFRow']), '_CCAFF_vendor_comm_pct', sanitize_text_field($_POST['addAffpercentUD']) );
        update_user_meta( sanitize_text_field($_POST['updateAFFRow']), '_CCAFF_vendor_PPemail', sanitize_email($_POST['addAffPPemailUD'] ));
        update_user_meta( sanitize_text_field($_POST['updateAFFRow']), '_CCAFF_vendor_notes', sanitize_textarea_field($_POST['addAffNotesUD']) );
    }
    
    
    
?>

<script>
/* Filters the useres search box*/

function lodaFilterFunction() {
  var input, filter, ul, li, a, i;
  input = document.getElementById("addAffInput");
  filter = input.value.toUpperCase();
  div = document.getElementById("authorCCAdd");
  a = div.getElementsByTagName("option");
  for (i = 0; i < a.length; i++) {
    txtValue = a[i].textContent || a[i].innerText;
    if (txtValue.toUpperCase().indexOf(filter) > -1) {
      a[i].style.display = "";
    } else {
      a[i].style.display = "none";
    }
  }
}
</script>
<style>
    .ccformaff {
        display: inline-block;
        margin-left: 10px; 
    }
	
	.affselect{
		min-height: 100px;
	}
</style>
        <h2>Affiliates</h2>
        <div id="CCsearchContainAdd">
        <span><b>Add New Affiliate</b></span><br><br>
        <span>Select New Affiliate From WP Users:</span><br> 
        <form method="post" action="">
        <input type="text" class="affsearch" placeholder="Search.." id="addAffInput" name="addAffInput" onkeyup="lodaFilterFunction()"><br>
        <select class="affselect" name="authorCCAdd" id="authorCCAdd" size="5" required>
        <option value="-">-</option>
        
        <?php
        foreach ( $blogusers as $user ) {
         echo '<option value="' . esc_html( $user->ID) . '">' . esc_html( $user->user_login) .' (#' . esc_html( $user->ID) . ' - ' .esc_html( $user->user_email) . ')</option>';
} 
        ?>
        </select><br><br>
        <span>Select Commission Percentage:</span>
        <input type="number" id="addAffpercent" name="addAffpercent" min="0" max="100" step="0.1" value="10" style="max-width: 5em" required><span>%</span><br><br>
        <span>PayPal Email</span>
        <input type="text" id="addAffPPemail" name="addAffPPemail"><br><br>
        <span>Notes, Payment Details, Bank info, etc.</span> <br>
        <textarea rows="4" cols="50" id="addAffNotes" name="addAffNotes"></textarea><br><br>
        <input type="submit" name="submint_Affiliate_CCAff" value="Add Affiliate">
        </form></div><br><br>
        <h2>Current Affiliates</h2>

        <table class="wp-list-table widefat fixed striped affiliates">
            <thead>
            <tr>
                <th>Affiliate</th>
                <th>Assigned Coupon Codes</th>
                <th>Commission %</th>
                <th>PayPal Email</th>
                <th>Notes, Payment Details, Bank info, etc.</th>
                <th>Actions</th>
            </tr>
            </thead>
        <?php
    $blogusersAff = [];
    $blogusersAff = get_users(array(
    'meta_key' => '_CCAFF_vendor_status',
    'meta_value' => '1'
));
    
    foreach ( $blogusersAff as $userAFF ) {
    $useAFFcomm = get_user_meta($userAFF->ID , '_CCAFF_vendor_comm_pct');
    $useAFFPP = get_user_meta($userAFF->ID , '_CCAFF_vendor_PPemail');
    $useAFFnotes = get_user_meta($userAFF->ID , '_CCAFF_vendor_notes');
    $theID = $userAFF->ID;
    $coupon_posts = get_posts( array(
        'posts_per_page'   => -1,
        'orderby'          => 'date',
        'order'            => 'desc',
        'post_type'        => 'shop_coupon',
        'meta_key'         => '_coupon_affiliate_ID',
        'meta_value'       => $theID
    ) );
    
    echo '<tr><form method="post" class="ccformaff" action="">';    
    echo '<td><span value="' . esc_html( $userAFF->ID) . '">' . esc_html( $userAFF->user_login) .' (#' . esc_html( $userAFF->ID) . ' - ' .esc_html( $userAFF->user_email) . ')</span></td>';
    
    echo "<td>";
    foreach( $coupon_posts as $coupon_post) {
        $ccname = $coupon_post->post_name;
        echo esc_html($ccname);
        echo '<br>';
    }
    echo "</td>";

    echo '<td> <input type="number" name="addAffpercentUD" min="0" max="100" step="0.1" value="'. esc_html($useAFFcomm[0]) .'" style="max-width: 5em" required>%</td>';
    echo '<td><input type="text" id="addAffPPemailUD" name="addAffPPemailUD" value="' . esc_html($useAFFPP[0]) . '"></td>';
    echo '<td><textarea rows="1" cols="30" id="addAffNotesUD" name="addAffNotesUD">' . esc_html($useAFFnotes[0]) . '</textarea></td>';
    echo '<td><button class="button button-primary" type="submit" name="updateAFFRow" value="' . esc_html($userAFF->ID) . '"/>Update Affiliate</button></form><form class="ccformaff" method="post" action="" onsubmit="return confirm(' . "'" . 'Do you really want to delete Affilaite?' . "'" . ');"><button type="submit" class="button button-primary" name="deleteItemAFF" value="' . esc_html($userAFF->ID) . '"/>Delete Affiliate</button></form></td>';
    echo "</tr>";
  }
  ?> 
  </table>
  <?php
    
}


//update order for Affiliate coupon codes
add_action('woocommerce_checkout_create_order', 'loda_add_coupon_affiliate_payment_status', 20, 2);
function loda_add_coupon_affiliate_payment_status( $order, $data ) {

	//$ccusedloda = $order->get_used_coupons();
	
	
		$coupon_codes = array();
		$coupons      = $order->get_items( 'coupon' );

		if ( $coupons ) {
			foreach ( $coupons as $coupon ) {
				$coupon_codes[] = $coupon->get_code();
			}
		}
		
		$ccusedloda = $coupon_codes;
	
	
		// if no coupons used return
		if( !$ccusedloda ) return;
		
    $coupon_postsloda = get_posts( array(
        'posts_per_page'   => -1,
        'orderby'          => 'date',
        'order'            => 'desc',
        'post_type'        => 'shop_coupon',
        'meta_key'         => '_coupon_affiliate_status',
        'meta_value'       => '1'
    ) );		

    foreach ($ccusedloda as $ccusedind){

        foreach( $coupon_postsloda as $coupon_postloda) {
        $ccName = $coupon_postloda->post_name;
        $ccID = $coupon_postloda->ID;
		$ccAff = get_post_meta($ccID, '_coupon_affiliate_ID');
        
        if ($ccusedind == $ccName) {
        $order->update_meta_data( '_coupon_codes_CCAFF_Comm_Status', "unpaid");
        $order->update_meta_data( '_coupon_codes_CCAFF_Aff_Coupon_used_ID', $ccID);
		$order->update_meta_data( '_coupon_codes_CCAFF_Aff_Affiliate_ID', $ccAff[0]);
    }
    }

}   
}

// function to make the commissions page
function loda_affiliate_coupons_commissions(){
    ?> <div class="wrap"> 
    <h2>commissions</h2>
    <?php
    
    $order = wc_get_orders( array( 
        'commstatus' => "unpaid" ,
        'status'=> 'wc-completed'
        )
    );
    
    
    //Mark All Paid Button Logic
    if(isset($_POST['markPaidAFF']) and is_numeric($_POST['markPaidAFF']))
    {

    foreach( $order as $orderccpaid) {
        $orderccpaidID = $orderccpaid->ID;
        update_post_meta($orderccpaidID, '_coupon_codes_CCAFF_Comm_Status', 'paid');
    }
    
    header("Refresh:0");
    }
    
    
    
    
    foreach ($order as $ordercc){
        
    $order_ccID = $ordercc->get_meta('_coupon_codes_CCAFF_Aff_Coupon_used_ID');
    $affID = get_post_meta($order_ccID, '_coupon_affiliate_ID');
    $order_subtotal = $ordercc->total - $ordercc->shipping_total - $ordercc->total_tax;
    
    // echo "Order ID: " . $ordercc->ID . " ";
    // echo "Coupon Code ID: " . $order_ccID . " ";
    // echo "affiliate ID:" . $affID[0];
    // echo " " . $order_subtotal;
    //echo $ordercc;
    //  echo '<br>';
    
    //$commarray[] = array('CC_ID' => $order_ccID, 'CC_sub' => $order_subtotal);
    if ($affID[0] != "-"){
    $commarray[] = array( $affID[0], $order_subtotal);
    }
    }
    
//print_r($commarray);
echo "<br>";

    
 foreach($commarray as $array){
  $tmp[$array[0]][] = $array[1];
}

foreach($tmp as $k => $v){
  $commarrayF[] = [$k, array_sum($v)];
}

//print_r($commarrayF);
?>
 <table class="wp-list-table widefat fixed striped commissions">
            <thead>
            <tr>
                <th>Affiliate</th>
                <th>PayPal Email</th>
                <th>Commission Payout</th>
            </tr>
            </thead>

<?php

foreach ($commarrayF as $commarrayFout){
    $userInfo = get_userdata($commarrayFout[0]);
    $usernameaff = $userInfo->user_login;
    $useAFFcommF = get_user_meta($commarrayFout[0] , '_CCAFF_vendor_comm_pct');
    $useAFFPPF = get_user_meta($commarrayFout[0] , '_CCAFF_vendor_PPemail');
    $finalPayout = $commarrayFout[1]*$useAFFcommF[0]*0.01;
    
    echo "<tr>";
    echo "<td>" . esc_html($usernameaff) . "</td>";
    echo "<td>" . esc_html($useAFFPPF[0]) . "</td>"; 
    echo "<td>" . wc_price($finalPayout) . "</td>"; 
    echo "</tr>";
}
   echo "</table>";
   
   
    ?></div>
    <form class="ccformaff" method="post" action="" onsubmit="return confirm('Do you really want to mark all outstanding commissions as Paid?');"><button type="submit" class="button button-primary" name="markPaidAFF" value="1"/>Mark All As Paid</button></form>
    
    
    <?php
}

//adds custom search parameter to get_orders
add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'loda_handle_order_number_custom_query_var', 10, 2 );
function loda_handle_order_number_custom_query_var( $query, $query_vars ) {
    if ( ! empty( $query_vars['commstatus'] ) ) {
        $query['meta_query'][] = array(
            'key' => '_coupon_codes_CCAFF_Comm_Status',
            'value' => esc_attr( $query_vars['commstatus'] ),
        );
    }

    return $query;
}

?>