<?php 

require_once 'config.php'; 
 

require_once 'dbConnect.php'; 
 //Сначала включаются файлы "config.php" и "dbConnect.php", которые содержат конфигурационные настройки и код подключения к базе данных соответственно.
$payment_id = $statusMsg = ''; 
$status = 'error'; 
 //Затем инициализируются переменные $payment_id, $statusMsg и $status. Переменная $status инициализируется значением 'error'.

if(!empty($_GET['sid'])){ //Скрипт проверяет, не является ли параметр $_GET['sid'] пустым. Если параметр не пустой, скрипт декодирует его с помощью base64_decode() и присваивает результат переменной $subscr_id.
    $subscr_id  = base64_decode($_GET['sid']); 
     
   
    $sqlQ = "SELECT S.id, S.stripe_subscription_id, S.paid_amount, S.paid_amount_currency, S.plan_interval, S.plan_interval_count, S.plan_period_start, S.plan_period_end, S.customer_name, S.customer_email, S.status, P.name as plan_name, P.price as plan_amount FROM user_subscriptions as S LEFT JOIN plans as P On P.id = S.plan_id WHERE S.id = ?"; //Далее скрипт готовит SQL-запрос для получения информации о подписке из базы данных на основе $subscr_id. Запрос объединяет таблицы user_subscriptions и plans для получения необходимых данных.
    $stmt = $db->prepare($sqlQ);  
    $stmt->bind_param("i", $subscr_id); 
    $stmt->execute(); //Запрос выполняется с помощью метода execute() и результат сохраняется в переменной $stmt
    $stmt->store_result(); 
     
    if($stmt->num_rows > 0){ //Скрипт проверяет, вернул ли запрос какие-либо строки с помощью $stmt->num_rows. Если строки есть, то результаты запроса привязываются к переменным с помощью $stmt->bind_result() и извлекаются с помощью $stmt->fetch(). Это позволяет получить информацию о подписке.
       
        $stmt->bind_result($subscription_id, $stripe_subscription_id, $paid_amount, $paid_amount_currency, $plan_interval, $plan_interval_count, $plan_period_start, $plan_period_end, $customer_name, $customer_email, $subscr_status, $plan_name, $plan_amount); 
        $stmt->fetch(); 
         
        $status = 'success'; //Если запрос вернул строки, скрипт устанавливает значение переменной $status на 'success' и устанавливает значение $statusMsg, указывающее, что платеж за подписку прошел успешно.
        $statusMsg = 'Your Subscription Payment has been Successful!'; 
    }else{ 
        $statusMsg = "Transaction has been failed!"; 
    } 
}else{ 
    header("Location: index.php"); //Если запрос не вернул строки, скрипт устанавливает значение $statusMsg, указывающее, что транзакция не удалась.
    exit; 
} 
?>

<?php if(!empty($subscription_id)){ ?><!-- Затем скрипт проверяет, не является ли переменная $subscription_id пустой. Если переменная не пустая, отображается информация о подписке и платеже.

-->
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
<?php }else{ ?> <!--Если переменная $subscription_id пустая, отображается сообщение об ошибке, указывающее, что транзакция не удалась.-->
    <h1 class="error">Your Transaction been failed!</h1>
    <p class="error"><?php echo $statusMsg; ?></p>
<?php } ?>