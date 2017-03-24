<?php

/*

  API v1 PLAYGROUND
  Version 20170324
  For zipMoney and zipPay Products
  Written by Darren Smith
  Copyright 2017

  NOT YET WORKING!!!!

*/

session_start();

if(!$_GET)
  session_unset();
if(!$_COOKIE['apiv1_email'])
  session_unset();

if (!file_exists('orders')) {
    mkdir('orders', 0777, true);
}

if (!file_exists('orders/webhook-logs')) {
    mkdir('orders/webhook-logs', 0777, true);
}

$Config_Email = $_COOKIE['apiv1_email'];

  if($_REQUEST['ajax']!=="true"){

?>

<html>
  <head>
    <title>zipMoney Legacy API v1 Checkout Experience</title>
    <script type="text/javascript" src="//code.jquery.com/jquery-2.1.4.js"></script>
    <script src="//static.zipmoney.com.au/checkout/checkout-v1.min.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="//code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="//static.zipmoney.com.au/labs/jquery.formtowizard.js"></script>
  </head>

  <body>

    <h1>zip Legacy API v1 Playground</h1>
    <p style="font-size:10px;">This playground allows you to interact with the legacy zip API v1. The script is open-source and available for <a target="_blank" href="https://github.com/darrensmith/zip-api-v1-playground/">download from GitHub</a>.</p>
    <p style="font-size:12px;"><strong>Currently operating as:</strong> <em> <?php echo $_COOKIE['apiv1_email'] ?: "Unknown User" ?></em>. <span id="logoutLink"></span> </p>

    <script>
      var configEmail = "<?php echo $_COOKIE['apiv1_email'] ?>";
      var lastZipProduct = "<?php echo $_SESSION['apiv1_apikey_type']?>";

      function getParameterByName(name, url) {
          if (!url) {
            url = window.location.href;
          }
          name = name.replace(/[\[\]]/g, "\\$&");
          var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
              results = regex.exec(url);
          if (!results) return null;
          if (!results[2]) return '';
          return decodeURIComponent(results[2].replace(/\+/g, " "));
      }

      $( document ).ready(function() {

        if(!configEmail){
          console.log('no email');
          $("#tab2-link").parent().hide();
          $("#tab3-link").parent().hide();
          $("#tab4-link").parent().hide();
          $("#tab1-link").click();
        } else {
          console.log('email known: '+configEmail);
          $("#logoutLink").html(function(){
            return $("<a id=\"ConfigLogout\" href=\"#\">Logout</a>").click(function(){
              eraseCookie('apiv1_email');
              console.log('clicked logout. redirecting to - '+window.location.href.split("?")[0]);
              window.location = window.location.href.split("?")[0];
            });
          });

          $("#tab1-link").parent().hide();
          $("#tab2-link").click();
        }

        $('#configSave').click(function(){
          createCookie('apiv1_email',$("#configEmail").val(),30);
          createCookie('apiv1_configFlowType',$("#configFlowType").val(),30);
          console.log('clicked save config. redirecting to - '+window.location.href.split("?")[0]);
          window.location = window.location.href.split("?")[0];
        });

        var activeTab = getParameterByName('tab');
        if(activeTab)
          $( "#tabs" ).tabs({ active: activeTab });

      });
    </script>

    <div id="tabs">
      <ul>
        <li><a id="tab1-link" href="#tabs-1">Configuration</a></li>
        <li><a id="tab2-link" href="#tabs-2">Create Order</a></li>
        <li><a id="tab3-link" href="#tabs-3">View Orders</a></li>
        <li><a id="tab4-link" href="#tabs-4">Checkout Results</a></li>
        <li><a id="tab5-link" href="#tabs-5">Information</a></li>
      </ul>

<?php

}

if(!$_SESSION['apiv1_starturl']){
  $ThisURL = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
  $ThisURL = explode("?",$ThisURL);
  $ThisURL = $ThisURL[0];
  $_SESSION['apiv1_starturl'] = $ThisURL;
} else {
  $ThisURL = $_SESSION['apiv1_starturl'];
}


function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyz', ceil($length/strlen($x)) )),1,$length);
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) {return true;}
    if (!is_dir($dir)) {return unlink($dir);}
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {continue;}
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {return false;}
    }
    return rmdir($dir);
}

$orderId = generateRandomString();

if(!$_REQUEST['apikey']){
  $apikey = $_SESSION['apiv1_apikey'];
} else {
  $_SESSION['apiv1_apikey_type'] = $_REQUEST['apikey'];
  if($_REQUEST['apikey'] == "zippay"){
    $apiMerchantId = "3005";
    $apikey = "7kJvAyfTgxHAS5lWhZG+WJoprpzikrrAT3ZyrDxJ5+o=";  
    $_SESSION['apiv1_apikey'] = $apikey;
    $_SESSION['apiv1_apiMerchantId'] = $apiMerchantId;
  } elseif($_REQUEST['apikey'] == "zipmoney"){
    $apiMerchantId = "3115";
    $apikey = "N9/gRqPQ0mv4WxLrTahSiE/x64j3lYHqdTOENfYO2N0=";
    $_SESSION['apiv1_apikey'] = $apikey;
    $_SESSION['apiv1_apiMerchantId'] = $apiMerchantId;
  }
}

if($_REQUEST['handler']=="clearOrders"){
  header('Content-Type: application/json');
  if($_REQUEST['orderid']){
    deleteDirectory('orders/'.$Config_Email.'/'.$_REQUEST['orderid']);
    echo "{\"message\":\"Order Deleted\"}";
  } else {
    deleteDirectory('orders/'.$Config_Email);
    echo "{\"message\":\"User Deleted\"}";
  }

} elseif($_REQUEST['handler']=="webhook"){
  header('Content-Type: application/json');

  $requestBody = file_get_contents('php://input');
  $messageObject = json_decode($requestBody);

  if($messageObject->MessageId){
    $myfile = fopen("orders/webhook-logs/".$messageObject->MessageId.".json", "w+");
    fwrite($myfile, $requestBody);
  }

  if($messageObject->Type == "SubscriptionConfirmation"){
    $ch = curl_init($messageObject->SubscribeURL);       
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                                                                                                                  
    $result = curl_exec($ch);
    $responseObject = new StdClass();
    $responseObject->message = "Confirmed AWS SNS subscription";
    echo json_encode($responseObject);
    exit(0);

  } elseif(!$messageObject || $messageObject->Type){
    $responseObject = new StdClass();
    $responseObject->error = "Did not receive valid webhook";
    echo json_encode($responseObject);
    exit(0);

  } else {
    $orderMap = json_decode(file_get_contents("orders/ordermap.json"));
    $subMessage = json_decode($messageObject->Message);
    $orderId = $subMessage->response->order_id;
    if(!$orderMap->$orderId){
      $responseObject = new StdClass();
      $responseObject->error = "Cannot find order for this webhook in order map";
      echo json_encode($responseObject);
      exit(0);
    }
    if (file_exists("orders/".$orderMap->$orderId."/".$orderId."/logs")) {
      file_put_contents("orders/".$orderMap->$orderId."/".$orderId."/logs/z|Webhook|".$subMessage->type.".json", $requestBody);
    } else {
      $responseObject = new StdClass();
      $responseObject->error = "Order log folder does not exist to log this message to";
      echo json_encode($responseObject);
      exit(0);      
    }

    if($subMessage->type == "authorise_under_review"){
      null;
    } elseif($subMessage->type == "order_cancelled"){
      null;      
    } elseif($subMessage->type == "authorise_declined"){
      null;      
    } elseif($subMessage->type == "charge_succeeded"){
      null;      
    } elseif($subMessage->type == "authorise_succeeded"){
       null;     
    } elseif($subMessage->type == "capture_succeeded"){
       null;     
    } elseif($subMessage->type == "cancel_succeeded"){
      null;      
    } else {
      $responseObject = new StdClass();
      $responseObject->error = "Webhook message of unknown type";
      echo json_encode($responseObject);
      exit(0);   
    }

    $responseObject = new StdClass();
    $responseObject->error = "Order updated";
    echo json_encode($responseObject);
    exit(0); 
    
  }



} elseif($_REQUEST['handler']=="orderList"){
  header('Content-Type: application/json');
  $OrderList = new StdClass();
  $OrderList->orders = array();
  $Folders = scandir('orders/'.$Config_Email);
  $FolderCount = 0;
  foreach($Folders as $Folder){
    if($Folder !== "." && $Folder !== ".." && $Folder !== ".DS_Store" && $Folder !== "user.json"){
      $OrderList->orders[$FolderCount] = new stdClass();
      $OrderList->orders[$FolderCount]->id = $Folder;
      $FolderCount++;
    }
  }
  echo json_encode($OrderList);


} elseif($_REQUEST['handler']=="captureOrder"){
  header('Content-Type: application/json');

  if(!$_REQUEST['chargeId']){
    $Response = new StdClass();
    $Response->error = "No chargeId provided";
    echo json_encode($Response);
    exit();
  }
  if(!$_REQUEST['amount']){
    $Response = new StdClass();
    $Response->error = "No amount provided";
    echo json_encode($Response);
    exit();
  }
  if(!$_REQUEST['orderid']){
    $Response = new StdClass();
    $Response->error = "No orderid provided";
    echo json_encode($Response);
    exit();
  }
  if(!$_REQUEST['apikey']){
    $Response = new StdClass();
    $Response->error = "No apikey provided";
    echo json_encode($Response);
    exit();
  }

  $CaptureJSON = "{\"amount\":".$_REQUEST['amount']."}";
  $ch = curl_init('https://api.sandbox.zipmoney.com.au/v1/capture');       
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");  
  curl_setopt($ch, CURLOPT_POSTFIELDS, $CaptureJSON);                                                                                                                                        
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
      'Content-Type: application/json'                                                                 
  ));                                                                                                                                                                                        
  $result = curl_exec($ch);
  $resultObject = json_decode($result);
  $Response = new StdClass();
  if($resultObject->error){
    $Response->error = $resultObject->error->message;
    echo json_encode($Response);
    exit();
  } else {
    $Response->success = "Order Captured";
  }

  if(!file_exists("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/7|POST|charges+".$_REQUEST['chargeId']."+capture|Request.txt")){
    $myfile = fopen("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/7|POST|charges+".$_REQUEST['chargeId']."+capture|Request.txt", "w");
    fwrite($myfile, $CaptureJSON);
  }

  if(!file_exists("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/8|POST|charges+".$_REQUEST['chargeId']."+capture|Response.txt")){
    $myfile = fopen("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/8|POST|charges+".$_REQUEST['chargeId']."+capture|Response.txt", "w");
    fwrite($myfile, $result);
  }

  $OrderDataJSON = file_get_contents("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/orderdata.json");
  $myfile = fopen("orders/".$Config_Email."/".$_REQUEST['orderid']."/orderdata.json", "w+");
  $OrderDataJSONObject = json_decode($OrderDataJSON);
  $OrderDataJSONObject->status = "Captured";
  $OrderDataJSON = json_encode($OrderDataJSONObject);
  fwrite($myfile, $OrderDataJSON);

  echo json_encode($Response);

} elseif($_REQUEST['handler']=="cancelOrder"){
  header('Content-Type: application/json');

  if(!$_REQUEST['chargeId']){
    $Response = new StdClass();
    $Response->error = "No chargeId provided";
    echo json_encode($Response);
    exit();
  }
  if(!$_REQUEST['orderid']){
    $Response = new StdClass();
    $Response->error = "No orderid provided";
    echo json_encode($Response);
    exit();
  }
  if(!$_REQUEST['apikey']){
    $Response = new StdClass();
    $Response->error = "No apikey provided";
    echo json_encode($Response);
    exit();
  }

  $ch = curl_init('https://api.sandbox.zipmoney.com.au/v1/cancel');       
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                                                                                      
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
      'Content-Type: application/json'                                                                 
  ));                                                                                                                                                                                                 
  $result = curl_exec($ch);
  $resultObject = json_decode($result);
  $Response = new StdClass();
  if($resultObject->error){
    $Response->error = $resultObject->error->message;
    echo json_encode($Response);
    exit();
  } else {
    $Response->success = "Order Cancelled";
  }

  if(!file_exists("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/7|POST|charges+".$_REQUEST['chargeId']."+cancel|Request.txt")){
    $myfile = fopen("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/7|POST|charges+".$_REQUEST['chargeId']."+cancel|Request.txt", "w");
    fwrite($myfile, "");
  }

  if(!file_exists("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/8|POST|charges+".$_REQUEST['chargeId']."+cancel|Response.txt")){
    $myfile = fopen("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/8|POST|charges+".$_REQUEST['chargeId']."+cancel|Response.txt", "w");
    fwrite($myfile, $result);
  }

  $OrderDataJSON = file_get_contents("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/orderdata.json");
  $myfile = fopen("orders/".$Config_Email."/".$_REQUEST['orderid']."/orderdata.json", "w+");
  $OrderDataJSONObject = json_decode($OrderDataJSON);
  $OrderDataJSONObject->status = "Cancelled";
  $OrderDataJSON = json_encode($OrderDataJSONObject);
  fwrite($myfile, $OrderDataJSON);

  echo json_encode($Response);


} elseif($_REQUEST['handler']=="refundOrder"){
  header('Content-Type: application/json');

  if(!$_REQUEST['chargeId']){
    $Response = new StdClass();
    $Response->error = "No chargeId provided";
    echo json_encode($Response);
    exit();
  }
  if(!$_REQUEST['orderid']){
    $Response = new StdClass();
    $Response->error = "No orderid provided";
    echo json_encode($Response);
    exit();
  }
  if(!$_REQUEST['amount']){
    $Response = new StdClass();
    $Response->error = "No amount provided";
    echo json_encode($Response);
    exit();
  }
  if(!$_REQUEST['apikey']){
    $Response = new StdClass();
    $Response->error = "No apikey provided";
    echo json_encode($Response);
    exit();
  }

  $RefundJSONObject = new StdClass();
  $RefundJSONObject->charge_id = $_REQUEST['chargeId'];
  $RefundJSONObject->reason = "Unwanted item";
  $RefundJSONObject->amount = $_REQUEST['amount'];
  $RefundJSON = json_encode($RefundJSONObject);
  
  $ch = curl_init('https://api.sandbox.zipmoney.com.au/v1/refund');       
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");  
  curl_setopt($ch, CURLOPT_POSTFIELDS, $RefundJSON);                                                                                                                                      
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
      'Content-Type: application/json'                                                                 
  ));                                                                                                                                                                                            
  $result = curl_exec($ch);
  $resultObject = json_decode($result);
  $Response = new StdClass();
  if($resultObject->error){
    $Response->error = $resultObject->error->message;
    echo json_encode($Response);
    exit();
  } else {
    $Response->success = "Refund of $".$_REQUEST['amount']." successful.";
  }

  $RandomPostString = generateRandomString(4);

  if(!file_exists("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/9".$RandomPostString."|POST|refunds|Request.txt")){
    $myfile = fopen("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/9".$RandomPostString."|POST|refunds|Request.txt", "w");
    fwrite($myfile, $RefundJSON);
  }

  if(!file_exists("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/9".$RandomPostString."|POST|refunds|Response.txt")){
    $myfile = fopen("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/logs/9".$RandomPostString."|POST|refunds|Response.txt", "w");
    fwrite($myfile, $result);
  }

  $OrderDataJSON = file_get_contents("orders/".$Config_Email.'/'.$_REQUEST['orderid']."/orderdata.json");
  $myfile = fopen("orders/".$Config_Email."/".$_REQUEST['orderid']."/orderdata.json", "w+");
  $OrderDataJSONObject = json_decode($OrderDataJSON);
  if(!$OrderDataJSONObject->amountRefunded)
    $OrderDataJSONObject->amountRefunded = 0;
  $OrderDataJSONObject->amountRefunded = $OrderDataJSONObject->amountRefunded + $_REQUEST['amount'];
  $OrderDataJSON = json_encode($OrderDataJSONObject);
  fwrite($myfile, $OrderDataJSON);

  echo json_encode($Response);


} elseif($_REQUEST['handler']=="orderDetails"){
  header('Content-Type: application/json');
  $oId = $_REQUEST['orderid'];
  $OrderDataJSON = file_get_contents("orders/".$Config_Email.'/'.$oId."/orderdata.json");
  $OrderDataJSONObject = json_decode($OrderDataJSON);
  $OrderDetails = new StdClass();
  $OrderDetails->id = $oId;
  $OrderDetails->amount = $OrderDataJSONObject->amount;
  $OrderDetails->amountRefunded = $OrderDataJSONObject->amountRefunded;
  $OrderDetails->shopper = $OrderDataJSONObject->shopper;
  $OrderDetails->email = $OrderDataJSONObject->email;
  $OrderDetails->checkoutId = $OrderDataJSONObject->checkoutId;
  $OrderDetails->chargeId = $OrderDataJSONObject->chargeId;
  $OrderDetails->status = $OrderDataJSONObject->status;
  $OrderDetails->product = $OrderDataJSONObject->product;
  $LogList = new StdClass();
  $LogList->logDescriptors = array();
  $LogList->logs = array();
  $Files = scandir('orders/'.$Config_Email.'/'.$oId.'/logs');
  $FolderCount = 0;
  foreach($Files as $File){
    if($File !== "." && $File !== ".." && $File !== ".DS_Store"){
      $FileExploded = explode("|",$File);
      $TypeExploded = explode(".",$FileExploded[3]);
      $FileExploded[2] = str_replace("+","/",$FileExploded[2]);
      $Title = $FileExploded[1]." /".$FileExploded[2]." ".$TypeExploded[0];
      $LogList->logDescriptors[$FolderCount] = $Title;
      $LogList->logs[$FolderCount] = file_get_contents("orders/".$Config_Email.'/'.$oId."/logs/".$File);
      $FolderCount++;
    }
  }
  $OrderDetails = (object) array_merge((array) $OrderDetails, (array) $LogList);
  echo json_encode($OrderDetails);


} elseif($_REQUEST['handler']=="setupCheckout"){
  header('Content-Type: application/json');

  $UserDataJSON = file_get_contents("orders/".$Config_Email."/user.json");
  $myfile = fopen("orders/".$Config_Email."/user.json", "w+");
  if($UserDataJSON) {
    $UserDataJSONObject = json_decode($UserDataJSON);
  } else {
    $UserDataJSONObject = new StdClass();
    $UserDataJSONObject->email = $Config_Email;
  }
  $UserDataJSON = json_encode($UserDataJSONObject);
  fwrite($myfile, $UserDataJSON);

  $Type = $_REQUEST['type'];
  $_SESSION['apiv1_type'] = $Type;
  $checkoutRequest = file_get_contents('php://input');

  if(!$checkoutRequest){
    $Response = new StdClass();
    $Response->error = "No web service request body provided";
    echo json_encode($Response);
    exit();
  }
  $_SESSION['apiv1_checkoutRequest'] = $checkoutRequest;
  echo "{ \"message\": \"Checkout Setup\", \"request\":".$_SESSION['apiv1_checkoutRequest']."}";

} elseif($_REQUEST['handler']=="checkout"){ 
  header('Content-Type: application/json');

  if(!$_SESSION['apiv1_checkoutRequest']){
    $Response = new StdClass();
    $Response->error = "Checkout request has not been set";
    echo json_encode($Response);
    exit();
  }

  $CheckoutRequestObject = json_decode($_SESSION['apiv1_checkoutRequest']);

  $_SESSION['apiv1_orderNumber'] = $CheckoutRequestObject->order_id;

  if (!file_exists('orders/'.$Config_Email)) {
    mkdir('orders', 0777, true);
  }
  if (!file_exists('orders/'.$Config_Email.'/'.$CheckoutRequestObject->order_id)) {
    mkdir('orders/'.$Config_Email.'/'.$CheckoutRequestObject->order_id, 0777, true);
  }
  if (!file_exists('orders/'.$Config_Email.'/'.$CheckoutRequestObject->order_id.'/logs')) {
    mkdir('orders/'.$Config_Email.'/'.$CheckoutRequestObject->order_id.'/logs', 0777, true);
  }

  if(!file_exists("orders/".$Config_Email.'/'.$CheckoutRequestObject->order_id."/logs/1|POST|checkouts|Request.txt")){
    $myfile = fopen("orders/".$Config_Email.'/'.$CheckoutRequestObject->order_id."/logs/1|POST|checkouts|Request.txt", "w");
    fwrite($myfile, $_SESSION['apiv1_checkoutRequest']);
  }

  $myfile = fopen("orders/".$Config_Email.'/'.$CheckoutRequestObject->order_id."/orderdata.json", "w+");
  $OrderDataJSONObject = new StdClass();
  $OrderDataJSONObject->id = $CheckoutRequestObject->order_id;
  $OrderDataJSONObject->status = "Cart";
  $OrderDataJSONObject->product = $_SESSION['apiv1_apikey_type'];
  $OrderDataJSONObject->amount = $CheckoutRequestObject->order->total;
  $OrderDataJSONObject->shopper = $CheckoutRequestObject->consumer->title." ".$CheckoutRequestObject->consumer->first_name." ".$CheckoutRequestObject->consumer->last_name;
  $OrderDataJSONObject->email = $CheckoutRequestObject->consumer->email;
  $OrderDataJSON = json_encode($OrderDataJSONObject);
  fwrite($myfile, $OrderDataJSON);

  $ch = curl_init('https://api.sandbox.zipmoney.com.au/v1/checkout');       
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
  curl_setopt($ch, CURLOPT_POSTFIELDS, $_SESSION['apiv1_checkoutRequest']);                                                                  
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
      'Content-Type: application/json'                                                            
  ));                                                                                                                   
                                                                                                          
  $result = curl_exec($ch);

  if(!file_exists("orders/".$Config_Email.'/'.$CheckoutRequestObject->order_id."/logs/2-POST-checkouts|Response.txt")){
    $myfile = fopen("orders/".$Config_Email.'/'.$CheckoutRequestObject->order_id."/logs/2|POST|checkouts|Response.txt", "w");
    fwrite($myfile, $result);
  }

  http_response_code(curl_getinfo($ch, CURLINFO_HTTP_CODE));

  $ResultObject = json_decode($result);
  $ResultObject->redirect_uri = $ResultObject->uri;
  $ResultObject->Request = $CheckoutRequestObject;
  $ResultObject = json_encode($ResultObject);
  echo $ResultObject;

} elseif($_REQUEST['result']=="approved"){ 
  $Status = "<h1 id=\"TransactionResultHeading\">Transaction Result</h1>\n";
  $Status .= "<p style=\"color:green; font-size:16px;\">Your transaction was successful. View Orders tab for more information.</p>\n";
  $Status .= "<p><a href=\"".$ThisURL."\">Return to Cart</a></p>";

} elseif($_REQUEST['result']=="referred" && $_SESSION['apiv1_apikey_type'] == "zipmoney"){ 
  $Status = "<h1>Transaction Result</h1>\n";
  $Status .= "<p style=\"color:green;\" border=\"1px solid red\">Your zip application is undergoing manual review. Please wait for an email with further instructions to complete your purchase.</p>";
  $Status .= "<p><a href=\"".$ThisURL."\">Return to Cart</a></p>";

  $OrderDataJSON = file_get_contents("orders/".$Config_Email.'/'.$_SESSION['apiv1_orderNumber']."/orderdata.json");
  $myfile = fopen("orders/".$Config_Email.'/'.$_SESSION['apiv1_orderNumber']."/orderdata.json", "w+");
  $OrderDataJSONObject = json_decode($OrderDataJSON);
  $OrderDataJSONObject->status = "Referred";
  $OrderDataJSON = json_encode($OrderDataJSONObject);
  fwrite($myfile, $OrderDataJSON);
  
}

elseif($_REQUEST['result'] == "declined"){
  $Status2 = "<p style=\"color:red;\" border=\"1px solid red\">Your application was declined. Please try to checkout again.</p>";

  $OrderDataJSON = file_get_contents("orders/".$Config_Email.'/'.$_SESSION['apiv1_orderNumber']."/orderdata.json");
  $myfile = fopen("orders/".$Config_Email.'/'.$_SESSION['apiv1_orderNumber']."/orderdata.json", "w+");
  $OrderDataJSONObject = json_decode($OrderDataJSON);
  $OrderDataJSONObject->status = "Declined";
  $OrderDataJSON = json_encode($OrderDataJSONObject);
  fwrite($myfile, $OrderDataJSON);
  deleteDirectory('orders/'.$Config_Email.'/'.$_SESSION['apiv1_orderNumber']);
}

elseif($_REQUEST['result'] == "cancelled"){
  $Status2 = "<p style=\"color:red;\" border=\"1px solid red\">You chose to cancel your transaction. Please try to checkout again.</p>";
  $OrderDataJSON = file_get_contents("orders/".$Config_Email.'/'.$_SESSION['orderNumber']."/orderdata.json");
  $myfile = fopen("orders/".$Config_Email."/".$_SESSION['orderNumber']."/orderdata.json", "w+");
  $OrderDataJSONObject = json_decode($OrderDataJSON);
  $OrderDataJSONObject->status = "Cancelled";
  $OrderDataJSON = json_encode($OrderDataJSONObject);
  fwrite($myfile, $OrderDataJSON);
  deleteDirectory('orders/'.$Config_Email.'/'.$_SESSION['orderNumber']);
}

elseif($_REQUEST['result'] == "referred" && $_SESSION['apiv1_apikey_type'] == "zippay"){
  $Status2 = "<p style=\"color:red;\" border=\"1px solid red\">zip did not have enough info to process your order. Please try again with another payment method.</p>";
  $OrderDataJSON = file_get_contents("orders/".$Config_Email.'/'.$_SESSION['apiv1_orderNumber']."/orderdata.json");
  $myfile = fopen("orders/".$Config_Email."/".$_SESSION['apiv1_orderNumber']."/orderdata.json", "w+");
  $OrderDataJSONObject = json_decode($OrderDataJSON);
  $OrderDataJSONObject->status = "Referred";
  $OrderDataJSON = json_encode($OrderDataJSONObject);
  fwrite($myfile, $OrderDataJSON);
  deleteDirectory('orders/'.$Config_Email.'/'.$_SESSION['apiv1_orderNumber']);
}

?>


<?php

  if($_REQUEST['ajax']!=="true"){

    if($_SESSION['apiv1_apikey_type'] == "zipmoney")
      $zO = "zm";
    else
      $zO = "zp";
    $zipOrder = $zO."-".generateRandomString(20);
?>


      <div id="tabs-1">
        <p>Welcome to the zip API v2 playground. Please configure your experience below prior to continuing:</p>
        <p>Email Address: <input type="text" id="configEmail" style="width:315px;" value="exampleuser@mailinator.com"/></p>
        <p>Flow Type: <select id="configFlowType">
          <option selected value="lightbox">Lightbox</option>
          <option value="redirect">Redirect</option>
        </select></p>
        <button id="configSave">Save Settings</button>
      </div>

      <div id="tabs-2">

        <?php echo $Status2; ?>

        <div id="credentialDropdown" style="display:none;">
          <p>Credentials:</p>
          <select name="creds" id="creds">
            <option selected value="zippay">zipPay Test Account</option>
            <option value="zipmoney">zipMoney Test Account</option>
          </select>
        </div>

        <script>
          var apiKey = $("#creds").val();
          $("#creds").change(function(){
            var apiKey = $("#creds").val();
          });
        </script>


        <!-- Developer View -->
        <p><a href="#" id="developerViewEnable">Toggle Developer View</a></p>

        <script>
          $("#developerViewEnable").click(function(){
            $("#developerView").toggle();
          });

          $(document).ready(function() {  
            $("#CaptureType").change(function(){
              console.log('change capture type');
              var checkoutRequestObject = JSON.parse($("#requestBody").val());
              var CaptureType = $("#CaptureType").val();
              if(CaptureType == "true"){
                checkoutRequestObject.metadata.capture = true;
                if(tokenisation.token)
                  checkoutRequestObject.capture = true;
              } else {
                checkoutRequestObject.metadata.capture = false;
                if(tokenisation.token)
                  checkoutRequestObject.capture = false;
              }
              $("#requestBody").val(JSON.stringify(checkoutRequestObject,null,4));
            });
          });

        </script>

        <div id="developerView" style="display:none;">
        <p>
          <strong>Capture Type:</strong>
          <select id="CaptureType">
            <option value="true">Immediate Capture</option>
            <option value="false">Auth/Capture</option>
          </select>
        </p>
        <p><strong>POST https://api.sandbox.zipmoney.com.au/v1/checkout Request:</p>

          <textarea style="width:800px;height:400px;" name="requestBody" id="requestBody" disabled>

            {
              "charge": true,
              "checkout_source": null,
              "currency_code": "AUD",
              "consumer": {
                "gender": 1,
                "dob": "1978-10-15T00:00:00",
                "email": "test@test.com",
                "first_name": "Bob",
                "last_name": "Smith",
                "phone": "0400000000",
                "title": "Mr",
                "account_created_on": "2014-03-23T20:00:00",
                "last_login": "2014-03-23T20:00:00",
                "lifetime_sales_amount": 1500,
                "average_sale_value": 750,
                "maximum_sale_value": 0,
                "lifetime_sales_units": 2,
                "lifetime_sales_refunded_amount": 0,
                "chargeback_before": false
              },
              "shipping_address": {
                "first_name": "Bob",
                "last_name": "Smith",
                "line1": "Level 9, 61 York St",
                "line2": "",
                "country": "AUS",
                "zip": "2000",
                "city": "Sydney",
                "state": "NSW",
                "OneLineAddress": "Level 9, 61 York St, Sydney 2000"
              },
              "billing_address": {
                "first_name": "Bob",
                "last_name": "Smith",
                "line1": "Level 9, 61 York St",
                "line2": "",
                "country": "AUS",
                "zip": "2000",
                "city": "Sydney",
                "state": "NSW",
                "OneLineAddress": "Level 9, 61 York St, Sydney 2000"
              },
              "cancel_url": "<?php echo $ThisURL ?>?result=cancelled",
              "success_url": "<?php echo $ThisURL ?>?result=approved",
              "cart_url": "<?php echo $ThisURL ?>?result=cancelled",
              "refer_url": "<?php echo $ThisURL ?>?result=referred",
              "error_url": "<?php echo $ThisURL ?>?result=cancelled",
              "decline_url": "<?php echo $ThisURL ?>?result=declined",
              "order_id": "<?php echo $zipOrder ?>",
              "txn_id": "xxx",
              "reference": "xxx",
              "order": {
                "id": "<?php echo $zipOrder ?>",
                "tax": 1,
                "shipping_value": 2,
                "total": 10,
                "SalesRepCode": "1234",
                "SalesRepName": "Jill",
                "detail": []
              },
              "version": {
                "client": "1.0",
                "platform": "zip API v1 Playground"
              },
              "merchant_id": "",
              "merchant_key": ""
            }

          </textarea>
        </div>

        <script>

          
          var checkoutRequestObject = JSON.parse($("#requestBody").val());
          var checkoutType = "checkout";

          $( document ).ready(function() {
            $("#SaveAccount").click(function(){
              console.log('place order clicked');

              var inputData = JSON.parse($("#requestBody").val());
              console.log("input data",inputData);
              var inputString = JSON.stringify(inputData);

              $.ajax( {
                method: "POST",
                url: "<?php echo $ThisURL."?handler=setupCheckout&ajax=true" ?>&type="+checkoutType,
                data: inputString,
                contentType: "application/json",
                dataType: "json"
              })
              .done(function( data ) {
                console.log( "Data Loaded: " + data );
                if(readCookie('apiv1_configFlowType') == "lightbox"){
                  Zip.Checkout.init({
                      checkoutUri: '<?php echo $ThisURL."?handler=checkout&ajax=true&apikey="?>'+$("#creds").val(),
                      logLevel: 'debug',
                      onComplete: function(args){
                        console.log('oncomplete call',args);
                        if(!args.checkoutId)
                          args.checkoutId = "";
                        window.location.href = '<?php echo $ThisURL?>?result='+args.state+'&checkoutId='+args.checkoutId;
                      },
                      onError: function(args){
                        console.log('onerror call',args);
                        $("#checkoutNotice").html("There was an error placing your order");
                        alert(args.message);
                      }
                  });
                } else if (readCookie('apiv1_configFlowType') == "redirect"){
                  $.ajax( {
                    method: "POST",
                    url: '<?php echo $ThisURL."?handler=checkout&ajax=true&apikey="?>'+$("#creds").val(),
                    contentType: "application/json",
                    dataType: "json"
                  })
                  .done(function( data ) {
                    var uri = data.redirect_uri;
                    window.location.href = data.redirect_uri;
                  })
                  .fail(function(){
                    alert('failed to redirect');
                  });
                } else {
                  alert("Flow type of lightbox or redirect not set");
                }

              })
              .fail(function(){
                alert('failed');
              });

            });
          });
        </script>

        <!-- Business User Wizard -->

        <style>
          body { font-family:Lucida Sans, Arial, Helvetica, Sans-Serif; font-size:13px; margin:20px;}
          #main { width:960px; margin: 0px auto; border:solid 1px #b2b3b5; -moz-border-radius:10px; padding:20px; background-color:#f6f6f6;}
          #header { text-align:center; border-bottom:solid 1px #b2b3b5; margin: 0 0 20px 0; }
          fieldset { border:none; width:320px;}
          legend { font-size:18px; margin:0px; padding:10px 0px; color:#b0232a; font-weight:bold;}
          label { display:block; margin:15px 0 5px;}
          input[type=text], input[type=password] { width:300px; padding:5px; border:solid 1px #000;}
          .prev, .next { background-color:#b0232a; padding:5px 10px; color:#fff; text-decoration:none;}
          .prev:hover, .next:hover { background-color:#000; text-decoration:none;}
          .prev { float:left;}
          .next { float:right;}
          #steps { list-style:none; width:100%; overflow:hidden; margin:0px; padding:0px;}
          #steps li {font-size:24px; float:left; padding:10px; color:#b0b1b3;}
          #steps li span {font-size:11px; display:block;}
          #steps li.current { color:#000;}
          #makeWizard { background-color:#b0232a; color:#fff; padding:5px 10px; text-decoration:none; font-size:18px;}
          #makeWizard:hover { background-color:#000;}
        </style>


        <script>
        $(function () {
          $("#SignupForm").formToWizard({ 
            submitButton: 'SaveAccount',
            validateBeforeNext: function(form, step) {
              console.log('currently on step ',step);
              if(step.selector == "#step0"){
                var checkoutRequestObject = JSON.parse($("#requestBody").val());
                console.log('checkout request object - ',checkoutRequestObject);
                $("#cartContent").html("");
                for (var i = 0; i <= checkoutRequestObject.order.items.length - 1; i++) {
                  $("#cartContent").append("<p>"+checkoutRequestObject.order.items[i].quantity + " x " + checkoutRequestObject.order.items[i].name + " @ AUD$" + checkoutRequestObject.order.items[i].amount + " each.</p>");
                };
                $("#cartContent").append("<p><strong>Total Cart Value:</strong> AUD$" + checkoutRequestObject.order.amount + "</p>");
              }
              if(step.selector == "#step1"){
                var checkoutRequestObject = JSON.parse($("#requestBody").val());
                if(!checkoutRequestObject.order.items[0] || !checkoutRequestObject.order.amount || checkoutRequestObject.order.amount == 0){
                  alert("You cannot progress without any items in your cart");
                  return false;
                } else {
                  return true;
                }
              }
              return true;
            }
          });
        });
        </script>

        <table><tr>
          <td width="80%" valign="top">
            <form id="SignupForm" action="">
              <fieldset>
                <legend>Catalogue</legend>
                <div style="color:green;" id="latestProductAddUpdate"></div>
                <div class="cartItem" style="border: 1px solid black;margin-top:10px;padding-left:20px;">
                  <p>Test Product 1</p>
                  <p>AUD$5.00</p>
                  <p><em><a id="addToCartProduct1" href="#">Add to cart</a></em></p>
                </div>
               <div class="cartItem" style="border: 1px solid black;margin-top:10px;padding-left:20px;">
                  <p>Test Product 2</p>
                  <p>AUD$10.00</p>
                  <p><em><a id="addToCartProduct2" href="#">Add from cart</a></em></p>
                </div>
              </fieldset>
              <fieldset>
                <legend>Your Cart</legend>
                <div id="cartContent"></div>
              </fieldset>

              </fieldset>
              <fieldset>
                <legend>Shipping and Billing Address</legend>
                <table><tr>
                <td valign="top">
                <label for="shopperTitle">Title</label>
                <input class="PersonalDetails" id="shopperTitle" type="text"/>
                <label for="FirstName">First Name</label>
                <input class="PersonalDetails" id="FirstName" type="text" />
                <label for="LastName">Last Name</label>
                <input class="PersonalDetails" id="LastName" type="text" />
                <label for="Email">Email Address</label>
                <input class="PersonalDetails" id="Email" type="text" />
                <label for="Phone">Phone Number</label>
                <input class="PersonalDetails" id="Phone" type="text" />
                <label for="Gender">Gender</label>
                <input class="PersonalDetails" id="Gender" type="text" />
                </td>
                <td valign="top">
                <label for="line1Address">Line 1 Shipping Address</label>
                <input class="PersonalDetails" id="line1Address" type="text" />
                <label for="line2Address">Line 2 Shipping Address</label>
                <input class="PersonalDetails" id="line2Address" type="text" />
                <label for="City">City</label>
                <input class="PersonalDetails" id="City" type="text" />
                <label for="PostalCode">Postal Code</label>
                <input class="PersonalDetails" id="PostalCode" type="text" />
                <label for="State">State</label>
                <input class="PersonalDetails" id="State" type="text" />
                <label for="Country">Country</label>
                <input class="PersonalDetails" id="Country" type="text" />
                </td>
                </tr></table>
              </fieldset>
              <fieldset>
                <legend>Payment information</legend>
                <p id="checkoutNotice" style="border: 1px black solid;width:500px; padding:5px; margin-bottom:20px;">Please select your payment method below.</p>
                <p id="zipPayCheckoutOption">
                  <input checked="checked" type="radio" id="payMethodSelect_zippay" name="payMethodSelect" value="zippay" style="position:relative;top:-15px;"/>
                  <img style="width:200px;" src="//d3k1w8lx8mqizo.cloudfront.net/INTEGRATIONS/2016/zippay/logos/zipPay-logo-onWhite.png"/>
                </p>
                <p id="zipMoneyCheckoutOption">
                  <input type="radio" id="payMethodSelect_zipmoney"name="payMethodSelect" value="zipmoney" style="position:relative;top:-15px;"/>
                  <img style="width:200px;" src="//d3k1w8lx8mqizo.cloudfront.net/INTEGRATIONS/2016/zipmoney/logos/zipMoney-logo-coloured.png"/>
                </p>
                <p id="SaveDetailsMessage"><em>Save my details for later purchases</em> <input type="checkbox" id="saveDetailsForLater" value="true"/></p>
              </fieldset>
              
              <p><input id="SaveAccount" type="button" value="Place Order"/></p>
            </form>
          </td>
          <td width="20%" valign="top" style="text-align:right;">
            <p><strong>Cart:</strong> <span id="CartItemCount">0</span></p>
          </td>
        </tr></table>

        <script>

          $(document).ready(function() {  
            $("#saveDetailsForLater").change(function(){
              var checkoutRequestObject = JSON.parse($("#requestBody").val());
              if($("#saveDetailsForLater").is(":checked")){
                console.log('user has opted to save details for later');
                checkoutRequestObject.features = {
                                                    "tokenisation":{
                                                      "required": true
                                                    }
                                                 };
              } else {
                delete checkoutRequestObject.features;
                console.log('user has opted not to save details for later');
              }
              $("#requestBody").val(JSON.stringify(checkoutRequestObject,null,4));
            });
          });


          var checkoutRequestObject = JSON.parse($("#requestBody").val());
          $("#shopperTitle").val(checkoutRequestObject.shopper.title);
          $("#FirstName").val(checkoutRequestObject.shopper.first_name);
          $("#LastName").val(checkoutRequestObject.shopper.last_name);
          $("#Email").val(checkoutRequestObject.shopper.email);
          $("#Phone").val(checkoutRequestObject.shopper.phone);
          $("#Gender").val(checkoutRequestObject.shopper.gender);
          $("#line1Address").val(checkoutRequestObject.order.shipping.address.line1);
          $("#line2Address").val(checkoutRequestObject.order.shipping.address.line2);
          $("#City").val(checkoutRequestObject.order.shipping.address.city);
          $("#PostalCode").val(checkoutRequestObject.order.shipping.address.postal_code);
          $("#State").val(checkoutRequestObject.order.shipping.address.state);
          $("#Country").val(checkoutRequestObject.order.shipping.address.country);

          $(document).ready(function() {  
            $("#shopperTitle, #FirstName, #LastName, #Email, #Gender, #line1Address, #line2Address, #City, #PostalCode, #State, #Country").change(function(){
              console.log('some personal details have changed');
              var checkoutRequestObject = JSON.parse($("#requestBody").val());
              checkoutRequestObject.shopper.title = $("#shopperTitle").val();
              checkoutRequestObject.shopper.first_name = $("#FirstName").val();
              checkoutRequestObject.shopper.last_name = $("#LastName").val();
              checkoutRequestObject.shopper.email = $("#Email").val();
              checkoutRequestObject.shopper.phone = $("#Phone").val();
              checkoutRequestObject.shopper.gender = $("#Gender").val();
              checkoutRequestObject.order.shipping.address.line1 = $("#line1Address").val();
              checkoutRequestObject.order.shipping.address.line2 = $("#line2Address").val();
              checkoutRequestObject.order.shipping.address.city = $("#City").val();
              checkoutRequestObject.order.shipping.address.postal_code = $("#PostalCode").val();
              checkoutRequestObject.order.shipping.address.state = $("#State").val();
              checkoutRequestObject.order.shipping.address.country = $("#Country").val();
              checkoutRequestObject.shopper.billing_address.line1 = $("#line1Address").val();
              checkoutRequestObject.shopper.billing_address.line2 = $("#line2Address").val();
              checkoutRequestObject.shopper.billing_address.city = $("#City").val();
              checkoutRequestObject.shopper.billing_address.postal_code = $("#PostalCode").val();
              checkoutRequestObject.shopper.billing_address.state = $("#State").val();
              checkoutRequestObject.shopper.billing_address.country = $("#Country").val();
              $("#requestBody").val(JSON.stringify(checkoutRequestObject,null,4));
            });
          });

        </script>

        <script>

          var item1Index = -1;
          var item2Index = -1;

          $("#addToCartProduct1").click(function(){
            if(item1Index == -1 && item2Index == -1){
              item1Index = 0;
              item2Index = 1;
            }
            var checkoutRequestObject = JSON.parse($("#requestBody").val());
            if(!checkoutRequestObject.order.items[item1Index])
              checkoutRequestObject.order.items[item1Index] = {};
            checkoutRequestObject.order.items[item1Index].name = "Test Product 1";
            checkoutRequestObject.order.items[item1Index].amount = 5;
            if(!checkoutRequestObject.order.items[item1Index].quantity)
              checkoutRequestObject.order.items[item1Index].quantity = 0;
            checkoutRequestObject.order.items[item1Index].quantity ++;
            checkoutRequestObject.order.items[item1Index].sku = "PRODUCT1";
            checkoutRequestObject.order.items[item1Index].reference = "1";
            checkoutRequestObject = RecalculateTotalAmount(checkoutRequestObject);
            $("#CartItemCount").html(parseInt($("#CartItemCount").html()) + 1);
            $("#latestProductAddUpdate").html("Test Product 1 Added to Cart");
            setTimeout(function(){
              $("#latestProductAddUpdate").html("");
            },1000);
            DisplayPrettyJSON("#requestBody",checkoutRequestObject);
          });

          $("#addToCartProduct2").click(function(){
            if(item1Index == -1 && item2Index == -1){
              item1Index = 1;
              item2Index = 0;
            }
            var checkoutRequestObject = JSON.parse($("#requestBody").val());
            if(!checkoutRequestObject.order.items[item2Index])
              checkoutRequestObject.order.items[item2Index] = {};
            checkoutRequestObject.order.items[item2Index].name = "Test Product 2";
            checkoutRequestObject.order.items[item2Index].amount = 10;
            if(!checkoutRequestObject.order.items[item2Index].quantity)
              checkoutRequestObject.order.items[item2Index].quantity = 0;
            checkoutRequestObject.order.items[item2Index].quantity ++;
            checkoutRequestObject.order.items[item2Index].sku = "PRODUCT2";
            checkoutRequestObject.order.items[item2Index].reference = "2";
            checkoutRequestObject = RecalculateTotalAmount(checkoutRequestObject);
            $("#CartItemCount").html(parseInt($("#CartItemCount").html()) + 1);
            $("#latestProductAddUpdate").html("Test Product 2 Added to Cart");
            setTimeout(function(){
              $("#latestProductAddUpdate").html("");
            },1000);
            DisplayPrettyJSON("#requestBody",checkoutRequestObject);
          });
        </script>

        <script>

          function RecalculateTotalAmount(checkoutRequestObject){
            console.log(checkoutRequestObject);
            if(checkoutRequestObject.order.items[0])
              var item1Amount = checkoutRequestObject.order.items[0].quantity * checkoutRequestObject.order.items[0].amount;
            else
              var item1Amount = 0;
            if(checkoutRequestObject.order.items[1])
              var item2Amount = checkoutRequestObject.order.items[1].quantity * checkoutRequestObject.order.items[1].amount;
            else
              var item2Amount = 0;
            checkoutRequestObject.order.amount = item1Amount + item2Amount;
            if(tokenisation.token)
              checkoutRequestObject.amount = checkoutRequestObject.order.amount;
            return checkoutRequestObject;
          }

          $("#payMethodSelect_zipmoney").click(function(){
            $("#SaveAccount").val("Continue to zipMoney");
            $("#creds").val("zipmoney");
          });
          $("#payMethodSelect_zippay").click(function(){
            $("#SaveAccount").val("Continue to zipPay");
            $("#creds").val("zippay");
          });
        </script>


   </div>

   <div id="tabs-3">
    <p>Below are a list of orders placed so far:</p>
    <p><button id="clearOrders">Delete This User</button></p>
    
    <table style="width:100%"><tr>
      <td width="20%" valign="top">
        <table id="ordersTable"></table>
      </td>
      <td width="80%" rowspan="100">
        <h2>Order Data</h2>
        <p>Order ID: <span id="orderFields_orderId"></span></p>
        <p>Order Status: <span id="orderFields_orderStatus"></span></p>
        <p>Order Value (Amount Authorised/Captured): AUD $<span id="orderFields_orderValue"></span></p>
        <p>Amount Refunded: AUD $<span id="orderFields_amountRefunded"></span></p>
        <p>Shopper Name: <span id="orderFields_shopperName"></span></p>
        <p>Email Address: <span id="orderFields_emailAddress"></span></p>
        <p>Token: <span id="orderFields_token"></span> <button disabled id="submitTokenisedCharge">Create Order Using Token</button></p>
        <p>Product: <span id="orderFields_product"></span></p>
        <p style="display:none;">Charge ID: <span id="orderFields_chargeId"></span></p>
        <p>Refund: <input type="text" id="refundAmount" disabled/> <button disabled id="refundButton">Refund</button></p>
        <p>Auth/Capture Flow: <button disabled id="captureButton">Capture</button> <button disabled id="cancelButton">Cancel</button></p>
        <p></p>
        <p><button id="deleteThisOrder">Delete Order</button></p>

        <p>API Logs:</p>
        <div id="accordion" style="width:100%;">
        </div>


      </td>
    </tr></table>
    

    <script>

      $("#submitTokenisedCharge").click(function(){
        tokenisation.token = $("#orderFields_token").html();
        tokenisation.product = $("#orderFields_product").html();
        $("#TokenSessionMessage").css('display','inline');
        var requestBodyObject = JSON.parse($("#requestBody").val());
        requestBodyObject.authority = {
          "type": "account_token",
          "value": tokenisation.token
        };
        requestBodyObject.currency = "AUD";
        requestBodyObject.amount = 0;
        requestBodyObject.capture = requestBodyObject.metadata.capture;
        $("#requestBody").val(JSON.stringify(requestBodyObject,null,4));
        $("#SaveDetailsMessage").html("<em>You will be using your saved payment method of "+tokenisation.product+" for this order.</em>");
        $("#checkoutNotice").css('display','none');
        if(tokenisation.product == "zippay"){
          $("#zipMoneyCheckoutOption").css('display','none');
          $("#payMethodSelect_zipmoney").prop('checked',false);
          $("#payMethodSelect_zippay").prop('checked',true);
        } else if (tokenisation.product == "zipmoney"){
          $("#zipPayCheckoutOption").css('display','none');
          $("#payMethodSelect_zipmoney").prop('checked',true);
          $("#payMethodSelect_zippay").prop('checked',false);
        }
        $('#tabs').tabs({active: 1});
      });

      var tab3Selected = false;
      $("#deleteThisOrder").click(function(){
        $.ajax( {
          method: "POST",
          url: "<?php echo $ThisURL."?handler=clearOrders&ajax=true&orderid="?>" + $("#orderFields_orderId").html(),
          contentType: "application/json",
          dataType: "json"
        })
        .done(function( data ) {
          alert('Order Deleted');
          tab3Selected = false;
          ResetOrderData()
          FillOrdersTable();
        })
        .fail(function(){
          alert('failed');
        });
      });
      $("#clearOrders").click(function(){
        $.ajax( {
          method: "POST",
          url: "<?php echo $ThisURL."?handler=clearOrders&ajax=true" ?>",
          contentType: "application/json",
          dataType: "json"
        })
        .done(function( data ) {
          alert('All Orders Cleared');
          ResetOrderData();
          $("#tab2-link").click();
        })
        .fail(function(){
          alert('failed');
        });
      });
      $("#tab3-link").click(function(){
        FillOrdersTable();
      });
      if(getParameterByName('tab')==2){
        FillOrdersTable();
      }

      $("#tabs").tabs({

      });

      function FillOrdersTable(){
        if(tab3Selected == false){
            tab3Selected = true;
            $.ajax( {
              method: "POST",
              url: "<?php echo $ThisURL."?handler=orderList&ajax=true" ?>",
              contentType: "application/json",
              dataType: "json"
            })
            .done(function( data ) {
              $("#ordersTable").html("");
              var orderString  = "<tr><th>Order Number</th></tr>";
              $("#ordersTable").append(orderString);
              var jsonObject = data;
              if(jsonObject.orders){
                for (i = 0; i < jsonObject.orders.length; i++) {
                  orderString = "<tr id=\"order-"+jsonObject.orders[i].id+"\"><td><a class=\"orderRecord\" id=\""+jsonObject.orders[i].id+"\" href=\"#\">"+jsonObject.orders[i].id+"</a></td></tr>";
                  $("#ordersTable").append(orderString);
                  $(".orderRecord").click(function(event){
                    fillOrderData(event);
                  });
                }
              }
            })
            .fail(function(){
              alert('failed');
            });
          }
      }

      $("#tab2-link").click(function(){
        tab3Selected = false;
        ResetOrderData();
      });

      $("#tab4-link").click(function(){
        tab3Selected = false;
        ResetOrderData();
      });

      $("#refundButton").click(function(){
        var confirmRefund = confirm('Are you sure you want to refund this order?');
        if(confirmRefund){
          $.ajax( {
            method: "POST",
            url: "<?php echo $ThisURL."?handler=refundOrder&ajax=true" ?>&chargeId="+currentOrderData.chargeId+"&amount="+$("#refundAmount").val()+"&orderid="+$("#orderFields_orderId").html()+"&apikey="+$("#orderFields_product").html(),
            contentType: "application/json",
            dataType: "json"
          })
          .done(function( data ) {
            if(data.error){
              alert("Error: "+ data.error);
            } else {
              alert("Success: "+ data.success);
              window.location = window.location.href.split("?")[0];
            }
          })
          .fail(function(){
            alert('failed');
          });
        }
      });

      $("#captureButton").click(function(){
        var confirmCapture = confirm('Are you sure you want to capture this order?');
        if(confirmCapture){
          $.ajax( {
            method: "POST",
            url: "<?php echo $ThisURL."?handler=captureOrder&ajax=true" ?>&chargeId="+currentOrderData.chargeId+"&amount="+$("#orderFields_orderValue").html()+"&orderid="+$("#orderFields_orderId").html()+"&apikey="+$("#orderFields_product").html(),
            contentType: "application/json",
            dataType: "json"
          })
          .done(function( data ) {
            if(data.error){
              alert("Error: "+ data.error);
            } else {
              alert("Order Captured");
              window.location = window.location.href.split("?")[0];
            }
          })
          .fail(function(){
            alert('failed');
          });
        }
      });

      $("#cancelButton").click(function(){
        var confirmCancel = confirm('Are you sure you want to cancel this order?');
        if(confirmCancel){
          $.ajax( {
            method: "POST",
            url: "<?php echo $ThisURL."?handler=cancelOrder&ajax=true" ?>&chargeId="+currentOrderData.chargeId+"&orderid="+$("#orderFields_orderId").html()+"&apikey="+$("#orderFields_product").html(),
            contentType: "application/json",
            dataType: "json"
          })
          .done(function( data ) {
            if(data.error){
              alert("Error: "+ data.error);
            } else {
              alert("Order Cancelled");
              window.location = window.location.href.split("?")[0];
            }
          })
          .fail(function(){
            alert('failed');
          });
        }
      });


      function ResetOrderData(){
          $("#orderFields_orderId").html("");
          $("#orderFields_orderValue").html("");
          $("#orderFields_amountRefunded").html("");
          $("#orderFields_shopperName").html("");
          $("#orderFields_emailAddress").html("");    
          $("#orderFields_orderStatus").html("");
          $("#orderFields_product").html("");
          $("#orderFields_chargeId").html("");
          $("#orderFields_token").html("");
          $("#orderFields_token").parent().css("display","none");
          $("#submitTokenisedCharge").prop('disabled',true);
          $("#refundAmount").val("");
          $("#refundButton").prop('disabled',true);
          $("#deleteThisOrder").prop('disabled',true);
          $("#captureButton").prop('disabled',true);
          $("#cancelButton").prop('disabled',true);
          $(function() {
            $( "#accordion" ).accordion({
              heightStyle: "content"
            });
            $("#accordion").accordion("destroy");
            $("#accordion").empty(); 
          });

      }

      var currentOrderData = {};

      function fillOrderData(event){
        $.ajax( {
          method: "GET",
          url: "<?php echo $ThisURL."?handler=orderDetails&ajax=true&orderid=" ?>"+event.target.id,
          dataType: "json"
        })
        .done(function( data ) {

          ResetOrderData();
          currentOrderData = data;
          $("#orderFields_orderId").html(data.id);
          $("#orderFields_orderValue").html(data.amount);
          $("#orderFields_amountRefunded").html(data.amountRefunded);
          if(data.amountRefunded == "0"){
            $("#refundAmount").val(data.amount);
          } else {
            var amount = parseInt(data.amount);
            var amountRefunded = parseInt(data.amountRefunded);
            var difference = amount - amountRefunded;
            $("#refundAmount").val(difference);
          }
          $("#orderFields_shopperName").html(data.shopper);
          $("#orderFields_emailAddress").html(data.email);
          $("#orderFields_product").html(data.product);
          $("#orderFields_orderStatus").html(data.status);
          $("#orderFields_chargeId").html(data.chargeId);
          $("#deleteThisOrder").prop('disabled',false);
          if(data.tokenisationEnabled == true && data.token){
            $("#orderFields_token").html(data.token);
            $("#orderFields_token").parent().css("display","inline");
            $("#submitTokenisedCharge").prop('disabled',false);
          } else {

          }

          for (var i = 0; i <= data.logDescriptors.length - 1 ; i++) {
            $("#accordion").append("<h3>"+data.logDescriptors[i]+"</h3>");
            if(data.logs[i])
              data.logs[i] = JSON.stringify(JSON.parse(data.logs[i]),null,4);
            $("#accordion").append("<textarea style=\"width:100%;height:300px;\">"+data.logs[i]+"</textarea>");
          };

          $(function() {
            $( "#accordion" ).accordion({
              heightStyle: "content"
            });
          });

          if(data.status == "Captured"){
            $("#refundAmount").prop('disabled',false);
            $("#refundButton").prop('disabled',false);
          }

          if(data.status == "Authorised"){
            $("#captureButton").prop('disabled',false);
            $("#cancelButton").prop('disabled',false);
          }

          if(data.status == "Cancelled"){
            $("#captureButton").prop('disabled',true);
            $("#cancelButton").prop('disabled',true);
            $("#refundAmount").prop('disabled',true);
            $("#refundButton").prop('disabled',true);
          }

        })
        .fail(function(){
          alert('failed');
        });
      }

    </script>
   </div>

   <div id="tabs-4">
    <?php echo $Status ?>
   </div>


   <div id="tabs-5" style="display:none;">
    <div style="font-weight:normal;font-size:12px;margin:20px;">
      <h2 style="margin-bottom:20px;">Welcome to the zip API v1 Playground</h2>
      <p>In this playground you can do many things:</p>
      <ul>
        <li>Make zipPay orders</li>
        <li>Make zipMoney orders</li>
        <li>Experience the lightbox / iframe solution</li>
        <li>Sandbox all your experimentation via your email address so as not to be interrupted by others</li>
        <li>View all past orders created</li>
        <li>Play around with immediate capture and auth/capture flows - even capturing and cancelling your orders after making them</li>
        <li>Play around with refunding your orders after making them - partial or full</li>
        <li>See all of your webhooks as they are generated</li>
      </ul>
      <p>You are able to click Toggle Developer View from the Create Order screen to see the actual request that is going to be posted to the server. Within this view
         you are also able to toggle Capture Method (Immediate Capture vs Auth/Capture). For the technical teams that are integrating with zip and would like to understand
         every step that takes place in each flow they are able to go to View Orders, select an order and then scroll down to the API Logs section. All API logs for that order
         including all captures, cancels, refunds, etc are shown in full detail (request and response).
      </p>
      <p>There are a number of use cases that are handled within this playground. These incclude:</p>
      <ul>
        <li><strong>zipPay / zipMoney Standard Checkout</strong>
          <ol style="margin-bottom:20px;">
            <li>User goes to Create Order screen, adds products to cart</li>
            <li>User proceeds through cart, user detail entry and selection of payment method</li>
            <li>User places order</li>
            <li>POST /checkout with order content</li>
            <li>Sign up / login, proceed through checkout and approve purchase on zip</li>
            <li>Return to handler page</li>
            <li>If successful, present success message</li>
            <li>Update order on receipt of webhook</li>
          </ol>
        </li>
        <li><strong>Capturing Funds for an Auth/Capture Style Checkout</strong>
          <ol style="margin-bottom:20px;">
            <li>User goes to View Orders screen and finds and selects an order that is of an Authorised status</li>
            <li>User clicks Capture order and then clicks OK when asked for confirmation</li>
            <li>POST /capture</li>
            <li>Presents success message if capture was successful</li>
            <li>Update order on receipt of webhook</li>
          </ol>
        </li>
        <li><strong>Cancelling Funds for an Auth/Capture Style Checkout</strong>
          <ol style="margin-bottom:20px;">
            <li>User goes to View Orders screen and finds and selects an order that is of an Authorised status</li>
            <li>User clicks Cancel order and then clicks OK when asked for confirmation</li>
            <li>POST /cancel</li>
            <li>Presents success message if cancel was successful</li>
            <li>Update order on receipt of webhook</li>
          </ol>
        </li>
        <li><strong>Refunding Funds from any checkout</strong>
          <ol style="margin-bottom:20px;">
            <li>User goes to View Orders screen and finds and selects an order that is of an Captured status</li>
            <li>User enters amount to refund (cannot be greater than Total - Amount Refunded to Date) then clicks Refund</li>
            <li>When asked for confirmation user clicks OK</li>
            <li>POST /refund</li>
            <li>Presents success message if refund was successful</li>
            <li>Update order on receipt of webhook</li>
          </ol>
        </li>
      </ul>
    </div>
   </div>
</div>


   <script>

    function createCookie(name,value,days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days*24*60*60*1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + value + expires + "; path=/";
    }

    function readCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i=0;i < ca.length;i++) {
            var c = ca[i];
            while (c.charAt(0)==' ') c = c.substring(1,c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
        }
        return null;
    }

    function eraseCookie(name) {
        createCookie(name,"",-1);
    }

    function incrementValue(dataField)
    {
        var value = parseInt(dataField, 10);
        value = isNaN(value) ? 0 : value;
        value++;
        dataField = value;
    }

    function DisplayPrettyJSON(Selector,JObject){
      var JString = JSON.stringify(JObject,null,4);
      $(Selector).val(JString);
    }

    $( document ).ready(function() {
      if(getParameterByName("result") == "approved" || (getParameterByName("result") == "referred" && lastZipProduct=="zipmoney")){
        $("#tab4-link").click();
      }
    });

    $( function() {
      $( "#tabs" ).tabs();
    } );

   </script>

  </body>

</html> 

<?php

  }
?>
