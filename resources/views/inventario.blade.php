<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventario en vivo</title>
    @vite(['resources/js/app.js'])
    <style>
        :root {
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --head-bg: #f8fafc;
            --row-hover: #f8fafc;
            --log-bg: #0f172a;
            --log-text: #a7f3d0;
            --stock: #4f46e5;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1120;
                --card: #111827;
                --text: #e5e7eb;
                --muted: #94a3b8;
                --border: #1f2937;
                --head-bg: #0f172a;
                --row-hover: #1e293b;
                --log-bg: #020617;
                --stock: #a5b4fc;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 2.5rem 1rem;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.5;
        }
        .contenedor { max-width: 900px; margin: 0 auto; }

        header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        h1 { margin: 0; font-size: 1.75rem; letter-spacing: -0.02em; }
        .subtitulo { margin: .25rem 0 0; color: var(--muted); }
        .en-vivo {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .35rem .8rem; border-radius: 999px;
            background: #dcfce7; color: #166534; font-size: .85rem; font-weight: 600;
        }
        .en-vivo::before {
            content: ""; width: .5rem; height: .5rem; border-radius: 50%;
            background: #22c55e; animation: latido 1.5s infinite;
        }
        @keyframes latido { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }

        .tarjeta {
            background: var(--card); border: 1px solid var(--border); border-radius: 12px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .06); overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .85rem 1rem; text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap; }
        th {
            background: var(--head-bg); color: var(--muted);
            font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: var(--row-hover); }
        td:first-child { color: var(--muted); font-variant-numeric: tabular-nums; }
        td:nth-child(2) { font-weight: 600; }
        td:nth-child(3), td:nth-child(4) { font-variant-numeric: tabular-nums; }
        td:nth-child(4) { font-weight: 700; color: var(--stock); }
        th:last-child, td:last-child { text-align: right; }

        button {
            padding: .45rem .9rem; border: none; border-radius: 8px;
            background: var(--primary); color: #fff; font: inherit; font-size: .85rem; font-weight: 600;
            cursor: pointer; transition: background .15s, transform .1s;
        }
        button:hover { background: var(--primary-hover); }
        button:active { transform: scale(.96); }

        h2 { font-size: 1rem; margin: 2rem 0 .6rem; }
        #logs {
            margin: 0; min-height: 4rem; max-height: 220px; overflow: auto;
            padding: 1rem; border-radius: 12px;
            background: var(--log-bg); color: var(--log-text);
            font-size: .8rem; line-height: 1.6; white-space: pre-wrap; word-break: break-all;
        }
        #logs:empty::before { content: "Esperando eventos…"; color: #64748b; }

        @media (max-width: 600px) {
            body { padding: 1.5rem 1rem; }
            h1 { font-size: 1.4rem; }
            th, td { padding: .7rem .5rem; font-size: .85rem; }
            th:first-child, td:first-child { display: none; } /* ocultar ID en celulares */
            button { padding: .4rem .6rem; }
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <header>
            <div>
                <h1>Inventario en tiempo real</h1>
                <p class="subtitulo">Abra esta página en dos pestañas y cambie el stock.</p>
            </div>
            <span class="en-vivo">En vivo</span>
        </header>

        <div class="tarjeta">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="productos"></tbody>
            </table>
        </div>

        <h2>Eventos recibidos</h2>
        <pre id="logs"></pre>
    </div>
    <script>
        async function cargar() {
            const res = await fetch('/api/productos');
            const productos = await res.json();
            document.getElementById('productos').innerHTML = productos.map(p => `
                <tr>
                    <td>${p.id}</td>
                    <td>${p.nombre}</td>
                    <td>${p.precio}</td>
                    <td>${p.stock}</td>
                    <td><button data-id="${p.id}" data-stock="${p.stock}">+1 stock</button></td>
                </tr>
            `).join('');
        }
        document.addEventListener('click', async e => {
            if (!e.target.matches('button[data-id]')) return;
            await fetch('/api/productos/' + e.target.dataset.id, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ stock: Number(e.target.dataset.stock) + 1 })
            });
            await cargar();
        });
        window.addEventListener('load', cargar);
        window.addEventListener('inventario-cambio', e => {
            document.getElementById('logs').textContent = JSON.stringify(e.detail) + '\n' + document.getElementById('logs').textContent;
            cargar();
        });
    </script>
</body>
</html>
