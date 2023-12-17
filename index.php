<link rel="stylesheet" href="css.css">

<?php
    $db = json_decode(file_get_contents ('db.txt'), true);
    $cuenta = isset($_GET['cuenta']) ? $_GET['cuenta'] : 'cliente';
    $apikey = isset($_GET['apikey']) ? $_GET['apikey'] : 'Null';
    
    $connect = conexion($db, $cuenta, $apikey);


    switch ($_SERVER["REQUEST_METHOD"]) {

        case "PUT":
            $datos = json_decode(file_get_contents('php://input'), true);

            if ($cuenta === 'admin') {
                $sql = "UPDATE reservas SET Habitacion = '{$datos['habitacion']}', Cliente = '{$datos['cliente']}' , FechaEntrada='{$datos['fecha_entrada']}', FechaSalida='{$datos['fecha_salida']}' WHERE id = {$datos['reserva']}";
                $connect->exec($sql);

                header("HTTP/1.1 200 OK");
                echo json_encode($datos);
            } elseif ($cuenta === 'cliente') {
                $cliente = $apikey;

                $stmt = $connect->prepare("SELECT COUNT(*) as count FROM reservas WHERE Cliente = :cliente");
                $stmt->bindParam(':cliente', $cliente);
                $stmt->execute();

                $result = $stmt->fetch();

                if ($result['count'] > 0) {
                    $sql = "UPDATE reservas SET Habitacion = '{$datos['habitacion']}', FechaEntrada = '{$datos['fecha_entrada']}', FechaSalida = '{$datos['fecha_salida']}' WHERE apikey={$datos['apikey']}";
                    $connect->exec($sql);

                    header("HTTP/1.1 200 OK");
                    echo json_encode($datos);
                } else {
                    header("HTTP/1.1 403 Forbidden");
                    echo "No tienes permiso para actualizar esta reserva";
                }
            } else {
                header("HTTP/1.1 400 Bad Request");
                echo "Tipo de cuenta no válido";
            }
            break;

        case "POST":
            $datos = json_decode(file_get_contents('php://input'), true);

            if ($cuenta === 'admin') {
                $sql = "INSERT INTO reservas (Habitacion, Cliente, FechaEntrada, FechaSalida) VALUES ('{$datos['habitacion']}', '{$datos['cliente']}', '{$datos['fecha_entrada']}', '{$datos['fecha_salida']}')";
                
                $connect->exec($sql);
                $editar = $connect->lastInsertId();

                if ($editar) {
                    $datos['apikey'] = $editar;
                    header("HTTP/1.1 200 OK");
                    echo json_encode($datos);
                }
            } else if ($cuenta === 'cliente') {
                $campos = ['Habitacion', 'FechaEntrada', 'FechaSalida'];
                $filtrado = array_intersect_key($datos, array_flip($campos));

                if (count($filtrado) === count($campos)) {
                    $sql = "INSERT INTO reservas (Habitacion, FechaEntrada, FechaSalida) VALUES ('{$filtrado['Habitacion']}', '{$filtrado['FechaEntrada']}', '{$filtrado['FechaSalida']}')";

                    $connect->exec($sql);
                    $editar = $connect->lastInsertId();

                    if ($editar) {
                        $response = [
                            'apikey' => $editar,
                            'Habitacion' => $filtrado['Habitacion'],
                            'FechaEntrada' => $filtrado['FechaEntrada'],
                            'FechaSalida' => $filtrado['FechaSalida']
                        ];
                        header("HTTP/1.1 200 OK");
                        echo json_encode($response);
                    }
                } else {
                    header("HTTP/1.1 400 Bad Request");
                    echo "No se proporcionaron todos los campos";   
                }
            } else {
                header("HTTP/1.1 400 Bad Request");
                echo "Tipo de cuenta no válido";
            }
            break;

        case "DELETE";
                $datos = json_decode(file_get_contents('php://input'), true);

                if ($cuenta === 'admin') {
                    $sql = "DELETE FROM reservas WHERE id = :reserva";
                    $stmt = $connect->prepare($sql);
                    $stmt->bindParam(':reserva', $datos['reserva']);
                    $stmt->execute();

                    header("HTTP/1.1 200 OK");
                    echo "Reserva eliminada correctamente por el admin.";
                } else if ($cuenta === 'cliente') {
                    $cliente = $apikey;

                    $stmt = $connect->prepare("SELECT COUNT(*) as count FROM reservas WHERE Cliente = :cliente AND id = :reserva");
                    $stmt->bindParam(':cliente', $cliente);
                    $stmt->bindParam(':reserva', $datos['reserva']);
                    $stmt->execute();

                    $result = $stmt->fetch();

                    if ($result['count'] > 0) {
                        $sql = "DELETE FROM reservas WHERE id = :reserva";
                        $stmt = $connect->prepare($sql);
                        $stmt->bindParam(':reserva', $datos['reserva']);
                        $stmt->execute();

                        header("HTTP/1.1 200 OK");
                        echo "Reserva eliminada correctamente por el cliente";
                    } else {
                        header("HTTP/1.1 403 Forbidden");
                        echo "No tienes reservas que eliminar";
                    }
                } else {
                    header("HTTP/1.1 400 Bad Request");
                    echo "Tipo de cuenta no válido";
                }
            break;

        case "GET":
            if ($cuenta === 'admin') {
                $sql = "SELECT * FROM reservas";
            } elseif ($cuenta === 'cliente') {
                $cliente = $apikey;
                $sql = "SELECT * FROM reservas WHERE Cliente = :cliente";
            } else {
                header("HTTP/1.1 400 Bad Request");
                echo "Tipo de cuenta no válido";
                break;
            }
        
            $stmt = $connect->prepare($sql);
            if ($cuenta === 'cliente')
                $stmt->bindParam(':cliente', $cliente);
            $stmt->execute();

            $reservas = array();
            while ($registro = $stmt->fetch()) {
                $reservas[$registro["id"]] = [
                    "Habitacion" => $registro["Habitacion"],
                    "Cliente" => $registro["Cliente"],
                    "FechaEntrada" => $registro["FechaEntrada"],
                    "FechaSalida" => $registro["FechaSalida"]   
                ];
            }
            echo json_encode($reservas);

            break;

        default:
            header("HTTP/1.1 404 ERROR");
            echo "ERROR";
            break;
    }

    function conexion($db, $cuenta, $apikey) {
        if ($cuenta === 'cliente') {
            $pdoCliente = new PDO("mysql:host={$db['admin']['host']};dbname={$db['admin']['db']};charset=utf8", $db['admin']['username'], $db['admin']['password']);
    
            $sql = "SELECT COUNT(*) as count FROM usuarios WHERE APIKEY = :apiKey";
            $stmt = $pdoCliente->prepare($sql);
            $stmt->bindParam(':apiKey', $apiKey);
            $stmt->execute();
    
            $result = $stmt->fetch();
    
            if ($result['count'] > 0) {
                $db = $db['cliente'];
                return new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8", $db['username'], $db['password']);
            } else {
                echo 'APIKEY inválido. Acceso denegado.';
            }
        } else if ($cuenta === 'admin') {
            $pdoAdmin = new PDO("mysql:host={$db['admin']['host']};dbname={$db['admin']['db']};charset=utf8", $db['admin']['username'], $db['admin']['password']);
            
            $sql = "SELECT Permisos FROM usuarios WHERE APIKEY = :apiKey AND Permisos = 1";
            $stmt = $pdoAdmin->prepare($sql);
            $stmt->bindParam(':apiKey', $apikey);
            $stmt->execute();
    
            $result = $stmt->fetch();
            
            if ($result) {
                $db = $db['admin'];
                return new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8", $db['username'], $db['password']);
            } else {
                echo 'Acceso denegado.';
            }
        } else {
            echo 'Tipo de cuenta NO valido.';
        }
    }


?>