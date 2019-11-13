# Encrypted chat

Uses https://openpgpjs.org/ , and Ratchet.

How to run:<br>
- `nohup php websocket.php &`
- Do any necessary redirections on websocket port 8089
- Change 127.0.0.1 and localhost URL's in `index.php`

Guest automatically verifies host's key fingerprint, but not vice versa. It needs to be done manually.

