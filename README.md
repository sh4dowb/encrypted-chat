# Encrypted chat

Uses https://openpgpjs.org/ , and Ratchet.

How to run:<br>
- `nohup php websocket.php &`
- Do any necessary redirections on websocket port 8089
- Change 127.0.0.1 and localhost URL's in `index.php`

Guest automatically verifies host's key fingerprint, but not vice versa. It needs to be done manually.

- End to end encrypted, nobody else can read what you write (unless.. read the warning below)
- No logging
- No registration

Warning: This software cannot guarantee privacy and/or security. Just because you use encryption, it doesn't mean there aren't other vulnerabilities to disclose your messages. It's even possible that the OpenPGP.js code can be changed to include backdoors.

Live demo: https://chat.cagriari.com/
