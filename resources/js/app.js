//

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

// En esta versión de Laravel, Echo se configura en ./echo (no existe bootstrap.js)
import './echo';

window.Echo.channel('inventario')
    .listen('.producto.actualizado', (evento) => {
        console.log('Cambio recibido:', evento);
        window.dispatchEvent(new CustomEvent('inventario-cambio', { detail: evento }));
    });
