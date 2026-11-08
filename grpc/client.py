import grpc
import producto_pb2
import producto_pb2_grpc

with grpc.insecure_channel('127.0.0.1:50051') as canal:
    stub = producto_pb2_grpc.ProductoServiceStub(canal)
    try:
        print(stub.ObtenerProducto(producto_pb2.ProductoRequest(id=1)))
    except grpc.RpcError as error:
        print(error.code(), error.details())
