<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>6502 Emulator - Matrix OS</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background-color: #000;
            color: #0f0;
            margin: 
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        #emulator {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #screen {
            border: 2px solid #0f0;
            image-rendering: pixelated;
            width: 512px;
            height: 480px;
            margin-bottom: 20px;
        }
        #terminal {
            width: 100%;
            height: 300px;
            background-color: #000;
            border: 2px solid #0f0;
            overflow-y: scroll;
            padding: 10px;
            font-size: 14px;
            margin-bottom: 10px;
        }
        #input {
            width: 100%;
            display: flex;
            margin-bottom: 10px;
        }
        #commandInput {
            flex-grow: 1;
            background-color: #000;
            border: 1px solid #0f0;
            color: #0f0;
            padding: 5px;
            font-family: 'Courier New', monospace;
        }
        button {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 5px 10px;
            margin-left: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #030;
        }
        #cpuState {
            font-family: 'Courier New', monospace;
            background-color: #001100;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            width: 100%;
        }
        .matrix-effect {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }
    </style>
</head>
<body>
  <div id="emulator">
        <h1>6502 Emulator - Matrix OS</h1>
        <canvas id="screen"></canvas>
        <div id="terminal"></div>
        <div id="input">
            <input type="text" id="commandInput" placeholder="Enter command...">
            <button id="executeBtn">Execute</button>
        </div>
        <div id="cpuState"></div>
    </div>
    <canvas class="matrix-effect" id="matrixCanvas"></canvas>
    <script src="6502-emulator.js"></script>
    <script src="matrix-interface.js"></script>
<script>
    // Create necessary elements if they don't exist
    function createElementIfNotExists(id, type, parent, styles = {}) {
        if (!document.getElementById(id)) {
            const element = document.createElement(type);
            element.id = id;
            Object.assign(element.style, styles);
            (parent || document.body).appendChild(element);
            return element;
        }
        return document.getElementById(id);
    }

    const emulatorDiv = createElementIfNotExists('emulator', 'div', document.body, {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        padding: '20px'
    });

    const screenCanvas = createElementIfNotExists('screen', 'canvas', emulatorDiv, {
        border: '2px solid #0f0',
        width: '512px',
        height: '480px',
        imageRendering: 'pixelated'
    });

    const terminal = createElementIfNotExists('terminal', 'div', emulatorDiv, {
        width: '100%',
        height: '200px',
        backgroundColor: '#000',
        color: '#0f0',
        fontFamily: 'monospace',
        overflow: 'auto',
        padding: '10px',
        marginTop: '10px'
    });

    const inputDiv = createElementIfNotExists('inputDiv', 'div', emulatorDiv, {
        width: '100%',
        display: 'flex',
        marginTop: '10px'
    });

    const commandInput = createElementIfNotExists('commandInput', 'input', inputDiv, {
        flexGrow: '1',
        marginRight: '10px'
    });
    commandInput.placeholder = 'Enter command...';

    const executeBtn = createElementIfNotExists('executeBtn', 'button', inputDiv);
    executeBtn.textContent = 'Execute';

    const cpuState = createElementIfNotExists('cpuState', 'div', emulatorDiv, {
        fontFamily: 'monospace',
        marginTop: '10px',
        backgroundColor: '#001100',
        padding: '10px',
        width: '100%'
    });

    const matrixCanvas = createElementIfNotExists('matrixCanvas', 'canvas', document.body, {
        position: 'fixed',
        top: '0',
        left: '0',
        width: '100%',
        height: '100%',
        pointerEvents: 'none',
        zIndex: '9999'
    });

    // Assume emulator is already defined
    const fileSystem = {
        domains: new Map(),
        createDomain(name) {
            if (!this.domains.has(name)) {
                this.domains.set(name, '');
                return true;
            }
            return false;
        },
        writeToDomain(name, content) {
            if (this.domains.has(name)) {
                this.domains.set(name, content);
                return true;
            }
            return false;
        },
        readFromDomain(name) {
            return this.domains.get(name) || null;
        },
        listDomains() {
            return Array.from(this.domains.keys());
        }
    };

    function log(message) {
        terminal.innerHTML += message + '<br>';
        terminal.scrollTop = terminal.scrollHeight;
    }

    function executeCommand(command) {
        log('> ' + command);
        const parts = command.split(' ');
        switch (parts[0].toLowerCase()) {
            case 'help':
                log('Available commands: help, run, load, concat, show, draw, list');
                break;
            case 'run':
                if (parts.length < 2) {
                    log('Usage: run <domain>');
                } else {
                    const content = fileSystem.readFromDomain(parts[1]);
                    if (content) {
                        log(`Running domain: ${parts[1]}`);
                        runProgram(content);
                    } else {
                        log(`Domain not found: ${parts[1]}`);
                    }
                }
                break;
            case 'load':
                if (parts.length < 3) {
                    log('Usage: load <domain> <content>');
                } else {
                    const domain = parts[1];
                    const content = parts.slice(2).join(' ');
                    fileSystem.createDomain(domain);
                    fileSystem.writeToDomain(domain, content);
                    log(`Loaded content into ${domain}`);
                }
                break;
            case 'concat':
                if (parts.length < 4 || (parts[1] !== '-t' && parts[1] !== '-b')) {
                    log('Usage: concat -t|-b <domain1> <domain2>');
                } else {
                    const content1 = fileSystem.readFromDomain(parts[2]);
                    const content2 = fileSystem.readFromDomain(parts[3]);
                    if (content1 && content2) {
                        const newContent = parts[1] === '-t' ? content1 + '\n' + content2 : content2 + '\n' + content1;
                        fileSystem.writeToDomain(parts[2], newContent);
                        log(`Concatenated ${parts[3]} to ${parts[2]}`);
                    } else {
                        log('One or both domains not found');
                    }
                }
                break;
            case 'show':
                if (parts.length < 2) {
                    log('Usage: show memory|cpu|gpu');
                } else {
                    switch (parts[1].toLowerCase()) {
                        case 'memory':
                            log(emulator.dumpMemory(0x0000, 0x00FF));
                            break;
                        case 'cpu':
                            log(JSON.stringify(emulator.cpu.getState(), null, 2));
                            break;
                        case 'gpu':
                            log('GPU Memory: ' + emulator.gpu.vram.slice(0, 16).toString());
                            break;
                        default:
                            log('Unknown show command');
                    }
                }
                break;
            case 'draw':
                startDrawing();
                break;
            case 'list':
                log('Available domains: ' + fileSystem.listDomains().join(', '));
                break;
            default:
                log('Unknown command. Type "help" for available commands.');
        }
        updateCPUState();
    }

    function runProgram(code) {
        // Simple assembly parser and executor
        const lines = code.split('\n');
        let address = 0x0600;

        for (const line of lines) {
            const parts = line.trim().split(/\s+/);
            if (parts.length === 0 || parts[0].startsWith(';')) continue;

            const opcode = parts[0].toUpperCase();
            const operand = parts[1];

            switch (opcode) {
                case 'LDA':
                    emulator.cpu.memory[address++] = 0xA9;
                    emulator.cpu.memory[address++] = parseInt(operand.slice(1), 16);
                    break;
                case 'STA':
                    emulator.cpu.memory[address++] = 0x8D;
                    const addr = parseInt(operand.slice(1), 16);
                    emulator.cpu.memory[address++] = addr & 0xFF;
                    emulator.cpu.memory[address++] = (addr >> 8) & 0xFF;
                    break;
                case 'JMP':
                    emulator.cpu.memory[address++] = 0x4C;
                    const jmpAddr = parseInt(operand.slice(1), 16);
                    emulator.cpu.memory[address++] = jmpAddr & 0xFF;
                    emulator.cpu.memory[address++] = (jmpAddr >> 8) & 0xFF;
                    break;
                default:
                    log(`Unknown opcode: ${opcode}`);
            }
        }

        emulator.cpu.PC = 0x0600;
        emulator.run(1000);  // Run for 1000 cycles
    }

    executeBtn.addEventListener('click', () => {
        const command = commandInput.value;
        executeCommand(command);
        commandInput.value = '';
    });

    commandInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const command = commandInput.value;
            executeCommand(command);
            commandInput.value = '';
        }
    });

    function updateCPUState() {
        const state = emulator.cpu.getState();
        cpuState.innerHTML = `
            A: ${state.A.toString(16).padStart(2, '0')}
            X: ${state.X.toString(16).padStart(2, '0')}
            Y: ${state.Y.toString(16).padStart(2, '0')}
            P: ${state.P.toString(16).padStart(2, '0')}
            SP: ${state.SP.toString(16).padStart(2, '0')}
            PC: ${state.PC.toString(16).padStart(4, '0')}
        `;
    }

    function startDrawing() {
        const canvas = document.getElementById('screen');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let lastX = 0;
        let lastY = 0;

        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDraw);
        canvas.addEventListener('mouseout', stopDraw);

        function startDraw(e) {
            isDrawing = true;
            [lastX, lastY] = [e.offsetX, e.offsetY];
        }

        function draw(e) {
            if (!isDrawing) return;
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(e.offsetX, e.offsetY);
            ctx.strokeStyle = '#0f0';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.stroke();
            [lastX, lastY] = [e.offsetX, e.offsetY];
        }

        function stopDraw() {
            isDrawing = false;
        }

        log('Drawing mode activated. Click and drag on the screen to draw.');
    }

    // Initialize
    updateCPUState();

    // Example usage
    fileSystem.createDomain('example');
    fileSystem.writeToDomain('example', 'LDA #$01\nSTA $2000\nJMP $0600');
    log('Example program loaded. Type "run example" to execute it.');

    // Matrix effect
    const context = matrixCanvas.getContext('2d');

    matrixCanvas.width = window.innerWidth;
    matrixCanvas.height = window.innerHeight;

    const katakana = 'アァカサタナハマヤャラワガザダバパイィキシチニヒミリヰギジヂビピウゥクスツヌフムユュルグズブヅプエェケセテネヘメレヱゲゼデベペオォコソトノホモヨョロヲゴゾドボポヴッン';
    const latin = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const nums = '0123456789';
    const alphabet = katakana + latin + nums;

    const fontSize = 16;
    const columns = matrixCanvas.width / fontSize;

    const rainDrops = [];

    for (let x = 0; x < columns; x++) {
        rainDrops[x] = 1;
    }

    function draw() {
        context.fillStyle = 'rgba(0, 0, 0, 0.05)';
        context.fillRect(0, 0, matrixCanvas.width, matrixCanvas.height);

        context.fillStyle = '#0F0';
        context.font = fontSize + 'px monospace';

        for (let i = 0; i < rainDrops.length; i++) {
            const text = alphabet.charAt(Math.floor(Math.random() * alphabet.length));
            context.fillText(text, i * fontSize, rainDrops[i] * fontSize);

            if (rainDrops[i] * fontSize > matrixCanvas.height && Math.random() > 0.975) {
                rainDrops[i] = 0;
            }
            rainDrops[i]++;
        }
    }

    setInterval(draw, 30);
</script>
</body>
</html>