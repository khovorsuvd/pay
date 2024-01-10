<?php 

require_once 'config.php'; 
 

require_once 'dbConnect.php'; 
 
$payment_id = $statusMsg = ''; 
$status = 'error'; 
 

if(!empty($_GET['sid'])){ 
    $subscr_id  = base64_decode($_GET['sid']); 
     
   
    $sqlQ = "SELECT S.id, S.stripe_subscription_id, S.paid_amount, S.paid_amount_currency, S.plan_interval, S.plan_interval_count, S.plan_period_start, S.plan_period_end, S.customer_name, S.customer_email, S.status, P.name as plan_name, P.price as plan_amount FROM user_subscriptions as S LEFT JOIN plans as P On P.id = S.plan_id WHERE S.id = ?"; 
    $stmt = $db->prepare($sqlQ);  
    $stmt->bind_param("i", $subscr_id); 
    $stmt->execute(); 
    $stmt->store_result(); 
     
    if($stmt->num_rows > 0){ 
       
        $stmt->bind_result($subscription_id, $stripe_subscription_id, $paid_amount, $paid_amount_currency, $plan_interval, $plan_interval_count, $plan_period_start, $plan_period_end, $customer_name, $customer_email, $subscr_status, $plan_name, $plan_amount); 
        $stmt->fetch(); 
         
        $status = 'success'; 
        $statusMsg = 'Your Subscription Payment has been Successful!'; 
    }else{ 
        $statusMsg = "Transaction has been failed!"; 
    } 
}else{ 
    header("Location: index.php"); 
    exit; 
} 
?>

<?php if(!empty($subscription_id)){ ?>
    <h1 class="<?php echo $status; ?>"><?php echo $statusMsg; ?></h1>
    
    <h4>Payment Information</h4>
    <p><b>Reference Number:</b> <?php echo $subscription_id; ?></p>
    <p><b>Subscription ID:</b> <?php echo $stripe_subscription_id; ?></p>
    <p><b>Paid Amount:</b> <?php echo $paid_amount.' '.$paid_amount_currency; ?></p>
    <p><b>Status:</b> <?php echo $subscr_status; ?></p>
    
    <h4>Subscription Information</h4>
    <p><b>Plan Name:</b> <?php echo $plan_name; ?></p>
    <p><b>Amount:</b> <?php echo $plan_amount.' '.STRIPE_CURRENCY; ?></p>
    <p><b>Plan Interval:</b> <?php echo ($plan_interval_count > 1)?$plan_interval_count.' '.$plan_interval.'s':$plan_interval; ?></p>
    <p><b>Period Start:</b> <?php echo $plan_period_start; ?></p>
    <p><b>Period End:</b> <?php echo $plan_period_end; ?></p>
    
    <h4>Customer Information</h4>
    <p><b>Name:</b> <?php echo $customer_name; ?></p>
    <p><b>Email:</b> <?php echo $customer_email; ?></p>
<?php }else{ ?>
    <h1 class="error">Your Transaction been failed!</h1>
    <p class="error"><?php echo $statusMsg; ?></p>
<?php } ?>