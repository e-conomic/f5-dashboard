<?php
include("config.php");
if (!isset($_GET['env']) || ($_GET['env'] != "staging" && $_GET['env'] != "production")) die("You must specify either ?env=staging or ?env=production in the URL");

if (isset($_GET['mode']) && $_GET['mode'] == "api"){
	// API part
	function getUrlContent($url){
		global $f5Options;
		$url = $f5Options[$_GET['env']]['hostUri'].$url;
		return json_decode(file_get_contents($url));
	}

	function getReturnUrlContent($returnUrl){
		global $f5Options;
		$returnUrl = parse_url($returnUrl);
		return getUrlContent($returnUrl['path']."?".$returnUrl['query']);
	}

	function translateState($session, $state){
 		if ($session == "monitor-enabled" && $state == "up"){
 			$return = "online";
 		} elseif ($session == "user-disabled" && $state == "up"){
 			$return = "drain";
 		} elseif ($session == "user-disabled" && $state == "user-down"){
 			$return = "forced-offline";
 		} elseif ($session == "monitor-enabled" && $state == "down") {
 			$return = "offline";
 		} elseif ($session == "user-disabled" && $state == "down") {
 			$return = "offline";
 		} elseif ($session == "monitor-disabled" && $state == "up"){
 			$return = "monitor-offline";
 		} elseif ($session == "monitor-enabled" && $state == "checking"){
 			$return = "checking";
 		} else {
 			$return = "unknown";
 			error_log("Detected an unknown state for a member node. session:".$session." - state:".$state);
 		}
 		return $return;
	}

	$filter = rawurlencode("partition eq ".$f5Options[$_GET['env']]['partition']);
	$pools = getUrlContent('/mgmt/tm/ltm/pool?$filter='.$filter);
	foreach($pools->items as $poolObject){
		$members = getReturnUrlContent($poolObject->membersReference->link);
		if (sizeof($members->items) > 0){
			foreach($members->items as $memberObject){
				$translatedState = translateState($memberObject->session, $memberObject->state);
				$poolMembers[] = array(
					'name' => substr($memberObject->name, 0, strpos($memberObject->name, ":")),
					'address' => $memberObject->address.substr($memberObject->name, strpos($memberObject->name, ":")),
					'translatedState' => $translatedState,
					'session' => $memberObject->session,
					'state' => $memberObject->state
				);
			}
			$output[] = array(
				'name' => $poolObject->name,
				'description' => $poolObject->description,
				'members' => $poolMembers
			);
		} else {
			$output[] = array(
				'name' => $poolObject->name,
				'description' => $poolObject->description,
				'members' => null
			);
		}
		unset($poolMembers);
	}
	header("");
	echo json_encode($output, JSON_PRETTY_PRINT);
} else {
	// Front end part
	?>
	<html>
		<head>
			<style type="text/css">
html {
	background:#040404 url(images/background.jpg) 50% 0 fixed;
}
body {
	font-family: Verdana, Geneva, sans-serif;
}
div.headline {
	font-size: 28px;
	width: 100%;
	color: #5f87ae;
	font-weight: normal;
	text-transform: uppercase;
}
div.subline {
	font-size: 16px;
	font-style: italic;
	color: #DDDDDD;
	width: 100%;
}
div.membercontainer {
	width: 100%;
}
span.nomembers {
	color: #EEEEEE;
	font-style: italic;
}
div.member-offline {
  border-left: 6px solid #DD0000;
	margin-bottom: 12px;
	margin-right: 12px;
	padding: 4px 6px 6px;
	display: inline-block;
	background: #303030;
	width: 150px;
	color: #EEEEEE;
}
div.member-forced-offline {
  border-left: 6px solid #FF0080;
	margin-bottom: 12px;
	margin-right: 12px;
	padding: 4px 6px 6px;
	display: inline-block;
	background: #303030;
	width: 150px;
	color: #EEEEEE;
}
div.member-monitor-offline {
  border-left: 6px solid #FF8000;
	margin-bottom: 12px;
	margin-right: 12px;
	padding: 4px 6px 6px;
	display: inline-block;
	background: #303030;
	width: 150px;
	color: #EEEEEE;
}
div.member-drain {
  border-left: 6px solid #DDDD00;
	margin-bottom: 12px;
	margin-right: 12px;
	padding: 4px 6px 6px;
	display: inline-block;
	background: #303030;
	width: 150px;
	color: #EEEEEE;
}
div.member-online {
	border-left: 6px solid #00DD00;
	margin-bottom: 12px;
	margin-right: 12px;
	padding: 4px 6px 6px;
	display: inline-block;
	background: #303030;
	width: 150px;
	color: #EEEEEE;
}
div.member-checking {
	border-left: 6px solid #3399FF;
	margin-bottom: 12px;
	margin-right: 12px;
	padding: 4px 6px 6px;
	display: inline-block;
	background: #303030;
	width: 150px;
	color: #EEEEEE;
}
div.member-unknown {
	border-left: 6px solid #AAAAAA;
	margin-bottom: 12px;
	margin-right: 12px;
	padding: 4px 6px 6px;
	display: inline-block;
	background: #303030;
	width: 150px;
	color: #EEEEEE;
}
#noconnectionmodal {
	display: none;
	width: 300px;
	color: #EEEEEE;
}
#simplemodal-overlay {
	background-color:#000000;
}
#simplemodal-container {
	background-color:#333333;
	border:8px solid #444;
	padding:12px;
}
span.member_name {
	font-size: 16px;
	font-style: italic;
}
span.member_address {
	font-size: 12px;
	font-style: italic;
}
span.member_session {
	font-size: 10px;
	font-style: italic;
}
span.member_state {
	font-size: 10px;
	font-style: italic;
}
			</style>

			<head>
				<script src="js/jquery-1.11.1.min.js" type="text/javascript"></script>
				<script src="js/jquery.simplemodal.1.4.4.min.js" type="text/javascript"></script>
				<script type="text/javascript">
window.onload = function() {
	function generateMemberContent(member){
		return '<span class="member_name">' + member.name + '</span><br><span class="member_address">' + member.address + '</span><br><span class="member_session">session: ' + member.session + '</span><br><span class="member_state">state: ' + member.state + '</span>';
	}

	var apihttp;
	apihttp = new XMLHttpRequest();
	apihttp.onreadystatechange = function() {
		if (apihttp.readyState == 4 && apihttp.status == 200) {
			var pools=eval("("+apihttp.responseText+")");
			for (var i=0; i<pools.length; i++) {
				pool = pools[i]
				poolHeadline = document.createElement('div');
				poolHeadline.className = 'headline';
				poolHeadline.innerHTML = pool.name;
				document.body.appendChild(poolHeadline);
				poolSubline = document.createElement('div');
				poolSubline.className = 'subline';
				poolSubline.innerHTML = pool.description;
				document.body.appendChild(poolSubline);
				memberContainer = document.createElement('div');
				memberContainer.className = 'membercontainer';
				memberContainer.id = 'pool-' + i;
				document.body.appendChild(memberContainer);
				if (pool.members != null){
					for (var o=0; o<pool.members.length; o++) {
						member = pool.members[o];
						id = member.name.replace(':', '_');
						memberBox = document.createElement('div');
						memberBox.className = 'member-' + member.translatedState;
						memberBox.innerHTML = generateMemberContent(member);
						memberBox.id = id;
						document.getElementById('pool-' + i).appendChild(memberBox);
					}
				} else {
					memberContainer.innerHTML = '<span class="nomembers">No members are defined for this pool</span>';
				}
			}
		}
	}
	apihttp.open("GET","<?php echo $apiUrl; ?>",true);
	apihttp.send();

	function contains(a, s){
		for (var i=0; i<a.length; i++){
			if (a[i] == s){
				return true;
			}
		}
		return false;
	}

	setInterval(function(){
		var apihttp;
		apihttp = new XMLHttpRequest();
		apihttp.onreadystatechange = function() {
			if (apihttp.readyState == 4 && apihttp.status == 200) {
				var pools=eval("("+apihttp.responseText+")");
				for (var i=0; i<pools.length; i++) {
					pool = pools[i]
					memberIds = [];
					if (pool.members != null){
						for (var o=0; o<pool.members.length; o++) {
							member = pool.members[o];
							id = member.name.replace(':', '_');
							memberDiv = document.getElementById(id);
							memberIds.push(id);
							if (memberDiv != null){
								memberDiv.className = 'member-' + member.translatedState;
								memberDiv.innerHTML = generateMemberContent(member);
							} else {
								if (document.getElementById('pool-' + i).innerHTML == '<span class="nomembers">No members are defined for this pool</span>'){
									document.getElementById('pool-' + i).innerHTML = '';
								}
								memberBox = document.createElement('div');
								memberBox.className = 'member-' + member.translatedState;
								memberBox.innerHTML = generateMemberContent(member);
								memberBox.id = id;
								document.getElementById('pool-' + i).appendChild(memberBox);
							}
						}
					}
					childNodes = document.getElementById('pool-' + i).childNodes;
					if (childNodes != null){
						for(var o=0; o<childNodes.length; o++){
							childNode = childNodes[o];
							if (childNode.tagName == 'DIV'){
								if (!contains(memberIds, childNode.id)){
									document.getElementById('pool-' + i).removeChild(document.getElementById(childNode.id));
								}
							}
						}
					}
					if (document.getElementById('pool-' + i).childNodes.length == 0){
						document.getElementById('pool-' + i).innerHTML = '<span class="nomembers">No members are defined for this pool</span>';
					}
				}
				$.modal.close();
			} else if (apihttp.readyState == 4 && apihttp.status == 0) {
				$("#noconnectionmodal").modal({
					opacity:90
				});
			}
		}
		apihttp.open("GET","<?php echo $apiUrl; ?>",true);
		apihttp.send();
	},<?php echo $refreshInterval; ?>);
}
				</script>
			</head>
		</head>
		<body>
			<div id="noconnectionmodal"><h1>Error</h1>Connection to the service has been disrupted</div>
		</body>
	</html>
	<?php
}
?>
