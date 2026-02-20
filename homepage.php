<?php
date_default_timezone_set('Asia/Dhaka');
require_once 'dbconfig/config.php';
?>
<!DOCTYPE html>
<html lang="en">
   <head>

	<br>
	<br>
	<br>
      <meta charset="utf-8">
      <meta name="robots" content="noindex, nofollow">
      <title>Aresa Chatbot</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link href="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
	  <link href="style.css" rel="stylesheet">
      <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
      <script src="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
   </head>

<style>
body 
{
  background-image: url('https://png.pngtree.com/thumb_back/fw800/background/20190223/ourmid/pngtree-smart-robot-arm-advertising-background-backgroundrobotblue-backgrounddark-backgroundlightlight-image_68405.jpg' );
   background-repeat: no-repeat;
  background-attachment: fixed;
  background-size: 100% 100%;
}
</style>
   <body>
      <div class="container">
         <div class="row justify-content-md-center mb-8">
            <div class="col-md-8">
            	<form class="myform" action="homepage.php" method="post">
			<input name="logout" type="submit" id="logout_btn" value="Log Out"/><br>
			<button type="button" onclick="openERPModal()" id="erp_btn" style="padding: 8px 15px; cursor: pointer;">Set ERP Login</button>
		</form>
		
		<!-- ERP Credentials Modal -->
		<div id="erp_modal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4);">
		    <div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 300px; border-radius: 5px;">
		        <span onclick="closeERPModal()" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
		        <h2>Set ERP Credentials</h2>
		        <p>Provide your KL University ERP login credentials to let the chatbot fetch your marks and attendance.</p>
		        <form id="erp_form">
		            <label for="erp_username">ERP Username:</label><br>
		            <input type="text" id="erp_username" name="erp_username" required style="width: 100%; padding: 8px; margin: 5px 0;"><br><br>
		            
		            <label for="erp_password">ERP Password:</label><br>
		            <input type="password" id="erp_password" name="erp_password" required style="width: 100%; padding: 8px; margin: 5px 0;"><br><br>
		            
		            <button type="button" onclick="saveERPCredentials()" style="width: 100%; padding: 10px; background-color: #4CAF50; color: white; border: none; cursor: pointer; border-radius: 3px;">Save Credentials</button>
		            <button type="button" onclick="closeERPModal()" style="width: 100%; padding: 10px; margin-top: 5px; background-color: #ccc; border: none; cursor: pointer; border-radius: 3px;">Cancel</button>
		        </form>
		        <p><small style="color: #666;">Your credentials are encrypted and only used to access your ERP profile data.</small></p>
		    </div>
		</div>
		
		<?php
			if(isset($_POST['logout']))
			{
				session_destroy();
				header('location:index.php');
			}
		?>
               <!--start code-->
               <div class="card">
                  <div class="card-body messages-box">
					 <ul class="list-unstyled messages-list">
							<?php
							$sql = "SELECT * FROM message";
							$stmt = $db->prepare($sql);
							$stmt->execute();
							if($stmt->rowCount()>0){
								$content='';
								while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
									$message = $row['message'];
									$added_on = $row['added_on'];
									$strtotime = strtotime($added_on);
									$time = date('h:i A',$strtotime);
									$type = $row['type'];
									if($type == 'user'){
										$class = "messages-me";
										$imgAvatar = "user_avatar.png";
										$name = "Me";
									}else{
										$class="messages-you";
										$imgAvatar="bot_avatar.png";
										$name="Chatbot";
									}
									$content .= '<li class="'.$class.' clearfix"><span class="message-img"><img src="image/'.$imgAvatar.'" class="avatar-sm rounded-circle"></span><div class="message-body clearfix"><div class="message-header"><strong class="messages-title">'.$name.'</strong> <small class="time-messages text-muted"><span class="fas fa-time"></span> <span class="minutes">'.$time.'</span></small> </div><p class="messages-p">'.$message.'</p></div></li>';
								}
								//echo $html;
							}else{
								?>
								<li class="messages-me clearfix start_chat">
								   Please start
								</li>
								<?php
							}
							$stmt->closeCursor();
							?>
                    
                     </ul>
                  </div>
                  <div class="card-header">
                    <div class="input-group">
					   <input id="input-me" type="text" name="messages" class="form-control input-sm" placeholder="Type your message here..." />

					   <span class="input-group-append">
					   <input type="button" class="btn btn-primary" value="Send" onclick="send_msg()">
					   </span>
					</div> 
                  </div>
               </div>
               <!--end code--> 
            </div>
         </div>
      </div>
      <script type="text/javascript">
		 function openERPModal() {
		     document.getElementById("erp_modal").style.display = "block";
		 }
		 
		 function closeERPModal() {
		     document.getElementById("erp_modal").style.display = "none";
		 }
		 
		 function saveERPCredentials() {
		     var erp_username = document.getElementById("erp_username").value;
		     var erp_password = document.getElementById("erp_password").value;
		     
		     if (!erp_username || !erp_password) {
		         alert("Please enter both username and password");
		         return;
		     }
		     
		     jQuery.ajax({
		         url: 'get_bot_message_erp.php',
		         type: 'post',
		         data: {
		             action: 'set_erp_creds',
		             erp_username: erp_username,
		             erp_password: erp_password
		         },
		         success: function(result) {
		             var response = JSON.parse(result);
		             if (response.success) {
		                 alert(response.message);
		                 closeERPModal();
		                 document.getElementById("erp_form").reset();
		             } else {
		                 alert("Error: " + response.error);
		             }
		         },
		         error: function() {
		             alert("Failed to save credentials. Please try again.");
		         }
		     });
		 }
		 
		 window.onclick = function(event) {
		     var modal = document.getElementById("erp_modal");
		     if (event.target == modal) {
		         modal.style.display = "none";
		     }
		 }
		 
		 function getCurrentTime(){
			var now = new Date();
			var hh = now.getHours();
			var min = now.getMinutes();
			var ampm = (hh>=12)?'PM':'AM';
			hh = hh%12;
			hh = hh?hh:12;
			hh = hh<10?'0'+hh:hh;
			min = min<10?'0'+min:min;
			var time = hh+":"+min+" "+ampm;
			return time;
		 }
		 function send_msg(){
			jQuery('.start_chat').hide();
			var txt=jQuery('#input-me').val();
			var html='<li class="messages-me clearfix"><span class="message-img"><img src="image/user_avatar.png" class="avatar-sm rounded-circle"></span><div class="message-body clearfix"><div class="message-header"><strong class="messages-title">Me</strong> <small class="time-messages text-muted"><span class="fas fa-time"></span> <span class="minutes">'+getCurrentTime()+'</span></small> </div><p class="messages-p">'+txt+'</p></div></li>';





			jQuery('.messages-list').append(html);
			jQuery('#input-me').val('');
			if(txt){
				jQuery.ajax({
					url:'get_bot_message_erp.php',
					type:'post',
					data:'txt='+txt,
					success:function(result){



						var html='<li class="messages-you clearfix"><span class="message-img"><img src="image/bot_avatar.png" class="avatar-sm rounded-circle"></span><div class="message-body clearfix"><div class="message-header"><strong class="messages-title">Chatbot</strong> <small class="time-messages text-muted"><span class="fas fa-time"></span> <span class="minutes">'+getCurrentTime()+'</span></small> </div><p class="messages-p">'+result+'</p></div></li><a href="invalidans.php" id="invalid_btn"><i>Invalid Answer ?</i></a>';
						
						jQuery('.messages-list').append(html);
						jQuery('.messages-box').scrollTop(jQuery('.messages-box')[0].scrollHeight);
					}
				});
			}
		 }
      </script>
   </body>
</html>