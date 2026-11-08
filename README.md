# Sistema de Inventario Multicanal

Trabajo práctico: aplicación Laravel que gestiona productos y expone sus datos mediante **REST**, **GraphQL** (Lighthouse), **WebSockets** (Laravel Reverb) y **gRPC** (servidor Node.js en `grpc/`).

REST y GraphQL usan el mismo `ProductoService`, que valida, guarda y dispara el evento `ProductoActualizado`. Reverb transmite ese evento a los navegadores y el servidor gRPC consulta un endpoint interno protegido de Laravel.

---

## 6. Actividad comparativa

Mediciones realizadas en local (Docker con Laravel Sail en WSL 2), pidiendo el producto con ID 1. Los tiempos son los de varias ejecuciones seguidas, sin contar la primera.

| Prueba | REST | GraphQL | gRPC | WebSocket |
|---|---|---|---|---|
| **Obtener un producto** | `GET /api/productos/1` | `query { producto(id: 1) { ... } }` | `ObtenerProducto({ id: 1 })` | No aplica (no se usa para pedir datos) |
| **Seleccionar campos** | No: devuelve siempre el objeto completo | Sí: el cliente elige los campos en la consulta | No: devuelve los campos definidos en el `.proto` | No: recibe lo que el servidor emite |
| **Crear producto** | `POST /api/productos` → 201 | `mutation { crearProducto(...) }` | No implementado (el contrato solo define `ObtenerProducto`) | No crea: notifica la creación a los clientes (`accion: "creado"`) |
| **Recibir cambios en vivo** | No: hay que volver a consultar | No (sin suscripciones) | No (llamada unaria) | Sí: evento `producto.actualizado` en el canal `inventario` |
| **Tamaño de respuesta** | 144 bytes (JSON completo) | 57 bytes (`nombre`, `precio`) · 77 bytes (4 campos) | 22 bytes (protobuf binario) | 94 bytes por evento |
| **Tiempo de respuesta** | ~40–70 ms | ~48–73 ms | ~37–44 ms (la primera llamada ~97 ms) | ~2,2–2,6 s desde el cambio hasta la llegada |

### Observaciones sobre las mediciones

- **REST vs. GraphQL:** GraphQL pesa menos porque devuelve solo los campos pedidos. REST incluye además `created_at` y `updated_at`. Los tiempos son parecidos porque las dos consultas hacen el mismo trabajo en la base de datos.
- **gRPC:** es la respuesta más chica porque protobuf es binario y no repite los nombres de los campos. En este proyecto gRPC hace un paso extra (consulta a Laravel por HTTP en `/api/interno/productos/{id}`), así que su tiempo no muestra toda la ventaja que tendría si leyera los datos directamente. La primera llamada tarda más porque abre la conexión HTTP/2.
- **WebSocket:** los ~2,5 s no son del WebSocket en sí. El evento implementa `ShouldBroadcast` y pasa por la cola (`queue:work` con driver `database`), que revisa si hay trabajos nuevos cada pocos segundos. Una vez que el evento sale de la cola, Reverb lo entrega al instante a todos los navegadores conectados.

### Cómo se midió

```bash
# REST
curl -s -o /dev/null -w "%{size_download} B %{time_total}s\n" -H "Accept: application/json" http://localhost/api/productos/1

# GraphQL
curl -s -o /dev/null -w "%{size_download} B %{time_total}s\n" -H "Content-Type: application/json" \
  -d '{"query":"{ producto(id: 1) { nombre precio } }"}' http://localhost/graphql
```

- **gRPC:** se midió con un script de Node.js que llama a `ObtenerProducto` varias veces y calcula el tamaño de la respuesta codificada en protobuf.
- **WebSocket:** se midió en el navegador, desde que se envía el `PATCH` del stock hasta que llega el evento `producto.actualizado`.

---

## Preguntas para el informe final

### 1. ¿Por qué REST utiliza diferentes métodos HTTP mientras GraphQL suele utilizar un único endpoint?

REST está organizado alrededor de **recursos**: cada recurso tiene su URL (`/api/productos`, `/api/productos/1`) y el **método HTTP** indica qué se hace con él. `GET` consulta, `POST` crea, `PUT`/`PATCH` actualiza y `DELETE` borra. Por eso `Route::apiResource` genera cinco rutas, y la respuesta usa los códigos HTTP para informar el resultado: 200, 201, 204, 404 o 422.

GraphQL está organizado alrededor de un **esquema**: la operación y los datos que se quieren van escritos **dentro de la consulta**, no en la URL ni en el método. Por eso alcanza con un único endpoint (`POST /graphql`): lo que distingue una lectura (`query`) de una escritura (`mutation`) es el texto que se envía. Una consecuencia es que los errores suelen volver con HTTP 200 y un campo `errors`. Lo vimos al probar `crearProducto` con un precio negativo.

### 2. ¿Qué ventajas tiene solicitar solamente determinados campos en GraphQL?

- **Respuestas más livianas:** en nuestras pruebas, pedir solo `nombre` y `precio` dio 57 bytes, contra 144 bytes del mismo producto por REST. Esto importa sobre todo en celulares o con conexiones lentas.
- **Sin datos de más (over-fetching):** el cliente no recibe campos que no va a usar, como `created_at` o `updated_at`.
- **Menos pedidos (under-fetching):** en una sola consulta se pueden pedir varios recursos relacionados, cuando REST podría necesitar varias llamadas.
- **Cada cliente pide lo suyo:** la app web y la app móvil pueden consultar el mismo endpoint con campos distintos, sin que el backend tenga que crear un endpoint para cada pantalla.

### 3. ¿Por qué gRPC utiliza archivos `.proto`?

El archivo `.proto` es el **contrato** entre el cliente y el servidor. Define los servicios (`ProductoService`), los métodos (`ObtenerProducto`) y los mensajes con el tipo y el número de cada campo (`int32 id = 1`, `string nombre = 2`, etc.). Sirve para:

- **Serializar en binario (Protocol Buffers):** como los dos lados conocen el contrato, no hace falta mandar los nombres de los campos. Solo viaja el número de campo y su valor, y por eso la respuesta ocupó 22 bytes.
- **Tipado estricto:** los dos lados saben exactamente qué tipo tiene cada dato.
- **Generar código:** a partir del mismo `.proto` se genera el cliente o el servidor en distintos lenguajes. En este proyecto, `server.cjs` y `client.cjs` cargan el `.proto` con `@grpc/proto-loader`, y en Python se generan los stubs con `grpc_tools.protoc`.
- **Evolucionar sin romper:** los números de campo permiten agregar campos nuevos manteniendo la compatibilidad con clientes viejos.

### 4. ¿Qué ventaja ofrecen los WebSockets cuando varios usuarios modifican un inventario?

Con WebSockets el **servidor avisa** a todos los clientes conectados apenas cambia un dato, sin que tengan que preguntar. En la prueba con dos pestañas, cuando una apretaba "+1 stock", la otra recibía `producto.actualizado` y se actualizaba sola, sin recargar.

Si varios usuarios trabajan sobre el mismo inventario, esto:

- **Evita trabajar con datos viejos:** reduce el riesgo de vender un producto sin stock porque la pantalla mostraba un valor desactualizado.
- **Evita el *polling*:** no hace falta que cada cliente consulte cada pocos segundos si algo cambió, que genera tráfico y carga en el servidor aunque no haya cambios.
- **Mantiene una conexión abierta y bidireccional:** el servidor puede enviar datos en cualquier momento.

Igual, los WebSockets no resuelven por sí solos los **conflictos de concurrencia**. Si dos usuarios modifican el mismo stock a la vez, hacen falta transacciones o actualizaciones atómicas en el servidor, como advierte la guía.

### 5. ¿Qué tecnologías utilizarían para un sistema de gestión de ventas con aplicación web y móvil? Justificar.

Una combinación según cada necesidad:

- **REST para las operaciones principales** (ventas, productos, clientes): es simple, estándar, fácil de documentar y de probar con Postman, se puede cachear con HTTP y lo consume cualquier plataforma.
- **GraphQL para las pantallas de consulta**, sobre todo en la app móvil: cada pantalla pide solo los campos que necesita, con respuestas más chicas y menos pedidos, algo importante con datos móviles.
- **WebSockets (Reverb) para lo que tiene que verse en tiempo real:** stock disponible, nuevos pedidos y avisos entre vendedores, para que todos vean el mismo estado sin recargar.
- **gRPC solo para la comunicación interna entre servicios**, si el sistema crece en microservicios (por ejemplo facturación o logística): es rápido y de contrato estricto, pero no se usa directamente desde el navegador.

Lo importante, como en este TP, es que todas compartan una **misma capa de lógica** (`ProductoService`), para que las validaciones y reglas de negocio no se dupliquen.

### 6. ¿Qué inconvenientes podrían aparecer al implementar todas estas tecnologías en un mismo proyecto?

- **Más complejidad y más piezas para mantener:** en este TP hicieron falta varios procesos a la vez (la app, `reverb:start`, `queue:work` y el servidor gRPC en Node.js). Si uno se cae, una parte deja de funcionar, como cuando los eventos fallaban porque Reverb no estaba corriendo.
- **Lógica duplicada o inconsistente:** si cada interfaz valida por su cuenta, las reglas pueden quedar distintas. Por eso usamos `ProductoService`.
- **Varios lenguajes y dependencias:** PHP, JavaScript y Python, con Composer y npm. Hay que cuidar la compatibilidad de versiones (Lighthouse y Reverb con la versión de Laravel).
- **Seguridad en varios frentes:** cada canal necesita su propia protección. REST y GraphQL necesitan autenticación, los WebSockets canales privados, y el endpoint interno de gRPC su clave, que no debe exponerse.
- **Más difícil de probar y depurar:** un error puede estar en la API, en la cola, en Reverb o en el microservicio, y cada uno tiene sus propios logs.
- **Más recursos y configuración de despliegue:** más puertos, procesos y variables de entorno (`REVERB_*`, `GRPC_INTERNAL_KEY`), y gRPC necesita HTTP/2 y TLS en producción.
- **Curva de aprendizaje:** el equipo tiene que conocer cuatro formas distintas de comunicación y sus herramientas.
