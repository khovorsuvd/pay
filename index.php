<?php 
// Include configuration file  
require_once 'config.php'; 
 
// Include the database connection file 
include_once 'dbConnect.php'; 
 
// Fetch plans from the database 
$sqlQ = "SELECT * FROM plans"; 
$stmt = $db->prepare($sqlQ); 
$stmt->execute(); 
$stmt->store_result(); 
?>
<!doctype html>
<html lang="en-US">
<head>
  <meta charset="utf-8" />
  <title>Pay me</title>
  <link rel="stylesheet" href="css/style.css" />
  <script src="https://js.stripe.com/v3/"></script>
  <script src="js/checkout.js" STRIPE_PUBLISHABLE_KEY="<?php echo STRIPE_PUBLISHABLE_KEY; ?>" defer></script>
</head>
<body>
<div class="container">
    <h1>PayMe<h1>
    <div class="panel">
    <div class="panel-heading">
        <h3 class="panel-title">Subscription with Stripe</h3>
        
        <!-- Subscription Plan Info -->
        <div>
            <b>Select Plan:</b>
            <select id="subscr_plan" class="form-control">
                <?php 
                if($stmt->num_rows > 0){ 
                    $stmt->bind_result($id, $name, $price, $interval, $interval_count); 
                    while($stmt->fetch()){ 
                        $interval_str = ($interval_count > 1)?$interval_count.' '.$interval.'s':$interval; 
                ?>
                    <option value="<?php echo $id; ?>"><?php echo $name.' [$'.$price.'/'.$interval_str.']'; ?></option>
                <?php 
                    } 
                } 
                ?>
            </select>
        </div>
    </div>
    <div class="panel-body">
        <!-- Display status message -->
        <div id="paymentResponse" class="hidden"></div>
        
        <!-- Display a subscription form -->
        <form id="subscrFrm">
            <div class="form-group">
                <label>NAME</label>
                <input type="text" id="name" class="form-control" placeholder="Enter name" required="" autofocus="">
            </div>
            <div class="form-group">
                <label>EMAIL</label>
                <input type="email" id="email" class="form-control" placeholder="Enter email" required="">
            </div>
            
            <div class="form-group">
                <label>CARD INFO</label>
                <div id="card-element">
                    <!-- Stripe.js will create card input elements here -->
                </div>
            </div>
            
            <!-- Form submit button -->
            <button id="submitBtn" class="btn btn-success">
                <div class="spinner hidden" id="spinner"></div>
                <span id="buttonText">Proceed</span>
            </button>
        </form>
    </div>
</div>
        <div>    
</body>
</html>