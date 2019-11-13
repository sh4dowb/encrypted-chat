<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="keywords" content="secure chat, encrypted chat, crypt chat">
	<meta name="description" content="Private, end-to-end encrypted, non-logging chat">
	<title>Encrypted Chat</title>
</head>
<body>
	<div>
		<div class="github-ribbon" style="position: absolute; right: 0px; width: 150px; height: 150px; overflow: hidden; z-index: 99999; top: 0px;"><a target="_blank" style="display: inline-block; width: 200px; overflow: hidden; padding: 6px 0px; text-align: center; transform: rotate(45deg); text-decoration: none; color: rgb(255, 255, 255); position: inherit; right: -40px; font: 700 13px &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; box-shadow: rgba(0, 0, 0, 0.5) 0px 2px 3px 0px; background-color: rgb(170, 0, 0); top: 45px; background-image: linear-gradient(rgba(0, 0, 0, 0), rgba(0, 0, 0, 0.15));" href="https://github.com/sh4dowb/encrypted-chat">Fork me on GitHub</a></div>
		Server Status: <pre id="socket_status" style="display:inline-block;">Connecting...</pre><br>
		Key Fingerprint: <pre id="key_status" style="display:inline-block;">Generating...</pre><br>
		<span style="display:none;" id="share">Share this URL with the other party: <a href="#"></a><br></span>
		<span style="display:none;" id="other_party_key_status">Destination Key Fingerprint: <pre id="other_party_key" style="display:inline-block;">Loading...</pre><br></span>
		<button id="startChat" onclick="createRoom()" disabled="disabled">Create Secure Chat Room</button>
		<button style="display:none;" id="joinChat" onclick="joinRoom()" disabled="disabled">Join Secure Chat Room</button><br><br>
		<div id="chatBox" style="display:none;">
			<textarea cols="50" rows="7" id="messageBox"></textarea><br>
			<button id="sendBtn" onclick="sendMessage()">Send</button><br>
			<pre id="chatLog"></pre>
		</div>
	</div>
</body>
<script src="openpgp.min.js"></script>
<script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
<script>
	var destinationPublicKey = null;
	var destinationKey = null;
	var myKey;
	var roomId;
	var starter = true;
	function toHexString(byteArray) {
		return Array.from(byteArray, function(byte) {
			return ('0' + (byte & 0xFF).toString(16)).slice(-2);
		}).join(' ')
	}
	function HTMLescape(html){
		return document.createElement('div')
		.appendChild(document.createTextNode(html))
		.parentNode
		.innerHTML
	}

	var socket = new WebSocket("ws://127.0.0.1:8089");
	socket.onopen = function (event) {
		$("#socket_status").text("Ready");
		$("#startChat").removeAttr("disabled");
		$("#joinChat").removeAttr("disabled");
		setTimeout(function(){
			socket.send(JSON.stringify({type: "ping"}));
		}, 5000);
	};

	socket.onclose = function (event) {
		$("#socket_status").text("Disconnected");
		$("#startChat").attr("disabled", "disabled");
		$("#joinChat").attr("disabled", "disabled");
	};

	socket.onmessage = function (event) {
		var data = JSON.parse(event.data);
		switch(data.type){
			case "error":
			alert("Error: " + data.content);
			break;
			case "keyExchange":
			if(destinationPublicKey == null){
				destinationPublicKey = data.content;
				socket.send(JSON.stringify({type: "keyExchange", content: myKey.publicKeyArmored}));
				if(!starter){
					$("#socket_status").text("Verifying fingerprint...");
					(async () => {
						const key = (await openpgp.key.readArmored(destinationPublicKey)).keys[0];
						destinationKey = key;
						if(toHexString(key.keyPacket.fingerprint).replace(/ /g, '').toUpperCase() != starter_fingerprint){
							alert('Key fingerprint does not match!');
							socket.close();
							$("#socket_status").text("Key fingerprint couldn't be verified, disconnected");
						} else {
							$("#socket_status").text("Chat online");
							$("#chatBox").show();
							$("#joinChat").hide();
						}
					})();
					
				} else {
					$("#socket_status").text("Reading key...");
					(async () => {
						const key = (await openpgp.key.readArmored(destinationPublicKey)).keys[0];
						destinationKey = key;
						var fingerprint = toHexString(key.keyPacket.fingerprint).replace(/ /g, '').toUpperCase();

						$("#socket_status").text("Chat online");
						$("#chatBox").show();
						$("#share").hide();
						$("#startChat").hide();
						var fingerprint_formatted = "";
						for(var i = 0; i < fingerprint.length;i += 2)
							fingerprint_formatted += fingerprint[i] + fingerprint[i+1] + " ";

						$("#other_party_key").text(fingerprint_formatted);
						$("#other_party_key_status").show();
						$("#other_party_key_status").append("<small>You should verify this key with the other party</small>");
					})();
				}
			}
			break;
			case "createdRoom":
			$("#share").show();
			var url = "http://localhost/cryptchat/#"+myKey.fingerprint.replace(/ /gi,'')+":"+data.content;
			$("#share>a").attr("href", url);
			$("#share>a").text(url);
			break;
			case "joinedRoom":
			roomId = data.content;
			$("#socket_status").text("Joined to room, exchanging keys");
			socket.send(JSON.stringify({type: 'keyExchange', content: myKey.publicKeyArmored}));
			break;
			case "userJoined":
			roomId = data.content;
			$("#socket_status").text("User joined to room, exchanging keys");
			break;
			case "userDisconnect":
			$("#socket_status").text("User disconnected");
			$("#sendBtn").attr("disabled", "disabled");
			break;
			case "message":
			(async() => {
				const options = {
					message: await openpgp.message.readArmored(data.content),
					privateKeys: [myKey.key]
				};
				openpgp.decrypt(options).then(plaintext => {
					$("#chatLog").prepend("<b>Anonymous:</b> "+HTMLescape(plaintext.data) + "<br>");
				});
			})();
			break;
		}
	};

	var options = {
		userIds: [{ name:'Anonymous User', email:'anonymous@example.com' }],
		curve: "ed25519"
	};

	openpgp.generateKey(options).then(function(key) {
		myKey = key;
		myKey.fingerprint = toHexString(key.key.keyPacket.fingerprint).toUpperCase();
		$("#key_status").text(myKey.fingerprint);
	});

	function sendMessage(){
		(async() => {
			var options = {
				message: openpgp.message.fromText($("#messageBox").val()),
				publicKeys: destinationKey,
				armor: true
			};

			openpgp.encrypt(options).then(function(ciphertext) {
				encrypted = ciphertext.data;
				socket.send(JSON.stringify({type:"message",content:encrypted}));
				$("#chatLog").prepend("<b>You:</b> "+HTMLescape($("#messageBox").val()) + "<br>");
				$("#messageBox").val("");
			});
		})();
	}

	function createRoom(){
		socket.send(JSON.stringify({type: 'createRoom'}));
		$("#startChat").attr("disabled", "disabled");
	}
	function joinRoom(){
		socket.send(JSON.stringify({type: 'joinRoom', content: roomId}));
		$("#joinChat").attr("disabled", "disabled");
	}

	var hash = window.location.hash.replace('#', '').split(':');
	var starter_fingerprint = "";
	if(hash.length == 2){
		starter_fingerprint = hash[0];
		var fingerprint_formatted = "";
		for(var i = 0; i < hash[0].length;i += 2)
			fingerprint_formatted += hash[0][i] + hash[0][i+1] + " ";
		$("#other_party_key").text(fingerprint_formatted);
		$("#other_party_key_status").show();
		roomId = hash[1];
		starter = false;
		$("#joinChat").show();
		$("#startChat").hide();
	}
</script>
</html>
