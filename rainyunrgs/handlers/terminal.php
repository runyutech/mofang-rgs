<?php
$url = base64_decode($_GET["url"]);
$token = $_GET["token"];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>游戏云终端</title>
    
    <link rel="stylesheet" href="https://static.mhjz1.cn/xterm-5.3.0/xterm.css" />
    
    <style>
        body {
            background-color: #1e1e1e;
            color: #f0f0f0;
            font-family: sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 100vh;
            box-sizing: border-box;
        }

        .controls {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
            background: #2d2d2d;
            padding: 10px;
            border-radius: 8px;
        }

        button {
            padding: 8px 16px;
            background-color: #007acc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }

        button:hover {
            background-color: #005f9e;
        }

        button.btn-danger {
            background-color: #d9534f;
        }
        button.btn-danger:hover {
            background-color: #c9302c;
        }

        #status-indicator {
            margin-left: auto;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: gray;
            display: inline-block;
        }

        #terminal-container {
            flex: 1;
            width: 100%;
            background-color: #000;
            padding: 10px;
            box-sizing: border-box;
            border-radius: 4px;
            overflow: hidden; 
            position: relative;
        }
    </style>
</head>
<body>

    <div class="controls">
        <button id="btn-focus">聚焦输入</button>
        <button id="btn-clear">清空屏幕</button>
        <button id="btn-reconnect" class="btn-danger">重新连接</button>
        
        <div id="status-indicator">
            <span class="status-dot" id="status-dot"></span>
            <span id="status-text">未连接</span>
        </div>
    </div>

    <div id="terminal-container"></div>

    <script src="/themes/clientarea/default/assets/libs/jquery/jquery.min.js"></script>
    <script src="https://static.mhjz1.cn/xterm-5.3.0/xterm.min.js"></script>
    <script src="https://static.mhjz1.cn/xterm-addon-fit-0.8.0/xterm-addon-fit.min.js"></script>

    <script>
        $(document).ready(function() {
            
            // ---------------- 初始化 Xterm ----------------
            const term = new Terminal({
                cursorBlink: true, // 光标闪烁
                fontSize: 14,
                fontFamily: 'Menlo, Monaco, "Courier New", monospace',
                theme: {
                    background: '#000000',
                    foreground: '#ffffff'
                },
                convertEol: true, // 转换换行符，防止阶梯状输出
            });

            // 加载自适应插件
            const fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);

            // 挂载到 DOM
            term.open(document.getElementById('terminal-container'));
            fitAddon.fit(); // 初始调整大小

            let socket = null;
            let pingInterval = null;

            // ---------------- 核心功能逻辑 ----------------

            // 1. 连接 WebSocket 函数
            function connect() {
                if (socket) {
                    socket.close();
                }

                updateStatus('connecting');
                term.write('\r\n\x1b[33m正在连接到终端...\x1b[0m\r\n'); // 黄色文字提示

                try {
                    socket = new WebSocket(`<?php echo $url ?>&cols=${term.cols}&rows=${term.rows}&app-token=<?php echo $token ?>`);
                } catch (e) {
                    term.write(`\r\n\x1b[31mURL 错误: ${e.message}\x1b[0m\r\n`);
                    return;
                }

                socket.onopen = function() {
                    updateStatus('connected');
                    term.write('\r\n\x1b[32m连接成功!\x1b[0m\r\n'); // 绿色文字
                    term.focus();

                    // 连接成功后，立即发送一次当前的尺寸
                    sendResize();
                    
                    // 开启心跳 (如果后端需要 ping)
                    // 许多 K8s 面板如果没有数据传输会自动断开，这里模拟简单的 ping
                    // 如果你的后端不需要 ping 字符串，可以注释掉下面这行
                    startPing(); 
                };

                socket.onmessage = function(event) {
                    // 接收后端返回值并写入终端
                    // xterm.js 会自动解析 ANSI 颜色代码和特殊符号
                    if (typeof event.data === 'string') {
                        term.write(event.data);
                    } else {
                        // 如果是 Blob (二进制流)，需要转换
                        const reader = new FileReader();
                        reader.onload = () => term.write(reader.result);
                        reader.readAsText(event.data);
                    }
                };

                socket.onclose = function(event) {
                    updateStatus('disconnected');
                    term.write('\r\n\x1b[31m连接已断开 (Code: ' + event.code + ')\x1b[0m\r\n');
                    stopPing();
                };

                socket.onerror = function(error) {
                    updateStatus('error');
                    console.error('WebSocket Error:', error);
                };
            }

            // 2. 处理用户输入
            // term.onData 捕获所有按键，包括 Ctrl+C, 回车, 普通字符
            term.onData(data => {
                if (socket && socket.readyState === WebSocket.OPEN) {
                    // 直接发送原始数据，不要做 JSON 包装
                    // Ctrl+C 对应的是 \x03，xterm 已经处理好了，直接发就行
                    socket.send(data);
                }
            });

            // 3. 处理窗口 Resize
            // 封装发送 resize 命令的逻辑
            function sendResize() {
                if (!socket || socket.readyState !== WebSocket.OPEN) return;

                const dims = {
                    cols: term.cols,
                    rows: term.rows
                };
                
                // 根据你的要求，Resize 事件发送特定的 JSON 格式
                const resizeMessage = JSON.stringify({
                    type: "resize",
                    cols: dims.cols,
                    rows: dims.rows
                });

                console.log("Sending Resize:", resizeMessage);
                socket.send(resizeMessage);
            }

            // 监听窗口大小变化
            $(window).resize(function() {
                fitAddon.fit(); // 调整 xterm 尺寸适配 div
                sendResize();   // 发送新尺寸给后端
            });

            // 监听 xterm 内部的尺寸变化 (以防万一)
            term.onResize(function(size) {
                // 这里也可以触发发送，但上面 window.resize 通常够用了
                // 如果需要更精确的同步，可以在这里调用 sendResize
            });

            // ---------------- 辅助功能 ----------------

            // 更新 UI 状态
            function updateStatus(state) {
                const $text = $('#status-text');
                const $dot = $('#status-dot');

                if (state === 'connected') {
                    $text.text('已连接');
                    $dot.css('background-color', '#4caf50'); // Green
                } else if (state === 'connecting') {
                    $text.text('连接中...');
                    $dot.css('background-color', '#ff9800'); // Orange
                } else {
                    $text.text('已断开');
                    $dot.css('background-color', '#f44336'); // Red
                }
            }

            // 简单的 Ping 逻辑 (可选)
            function startPing() {
                stopPing();
                pingInterval = setInterval(() => {
                    if (socket && socket.readyState === WebSocket.OPEN) {
                        // 注意：有些 K8s 终端不需要发 ping，或者 ping 是协议层的
                        // 这里仅作演示，如果后端回显了 "ping" 干扰视线，请删除此段
                        socket.send('ping'); 
                    }
                }, 10000);
            }

            function stopPing() {
                if (pingInterval) clearInterval(pingInterval);
            }

            // ---------------- 按钮事件绑定 ----------------

            $('#btn-focus').click(function() {
                term.focus();
            });

            $('#btn-clear').click(function() {
                term.clear();
            });

            $('#btn-reconnect').click(function() {
                connect();
            });

            // ---------------- 启动 ----------------
            // 稍微延时一点启动，确保 DOM 渲染完毕
            setTimeout(connect, 100);
        });
    </script>
</body>
</html>