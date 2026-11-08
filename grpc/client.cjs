const grpc = require('@grpc/grpc-js');
const loader = require('@grpc/proto-loader');
const path = require('path');

const def = loader.loadSync(path.join(__dirname, 'producto.proto'));
const api = grpc.loadPackageDefinition(def).inventario;

const client = new api.ProductoService('127.0.0.1:50051', grpc.credentials.createInsecure());
client.ObtenerProducto({ id: 1 }, (err, producto) => {
    console.log(err || producto);
    client.close();
});
