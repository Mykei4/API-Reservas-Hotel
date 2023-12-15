<link rel="stylesheet" href="css.css">

<?php
    $db = json_decode(file_get_contents ('db.txt'), true);
    $cuenta = isset($_GET['cuenta']) ? $_GET['cuenta'] : 'cliente';
    $apikey = isset($_GET['apikey']) ? $_GET['apikey'] : 'Null';
    
    $cuenta = 'admin';

    $connect = conexion($db, $cuenta, $apikey);


    switch ($_SERVER["REQUEST_METHOD"]) {
        case "PUT":
            $input = json_decode(file_get_contents('php://input'), true);
            $postId = $input['id'];
            $telefono = $input['telefono'];
            $sql = " UPDATE alumnos SET telefono='{$telefono}' WHERE id={$postId} " ;
            echo $sql;
            $dbCon->exec($sql);
            header("HTTP/1.1 200 OK");
            break;

        case "POST":
            $input =json_decode(file_get_contents('php://input'), true);
            $sql = "INSERT INTO alumnos (nombre, apellido1, apellido2, telefono) VALUES ('{$input['nombre']}', '{$input['apellido1']}', '{$input['apellido2']}', '{$input['telefono']}')";
            echo $sql;
            $dbCon->exec($sql);
            $aluId = $dbCon->lastInsertId();
            if($aluId) {
                $input['id'] = $aluId;
                header("HTTP/1.1 200 OK");
                echo json_encode($input);
            }
            break;

        case "DELETE";
            $input =json_decode(file_get_contents('php://input'), true);
            $sql = $dbCon->prepare("DELETE FROM alumnos where id=:id");
            $sql->bindValue(':id', $input['id']);
            $sql->execute();
            header("HTTP/1.1 200 OK");
            break;

        case "GET":
            $sql = "SELECT * from reservas";
            $resultado = $connect->query($sql);
            $reserva = array();
            $reservas = array(); 
            while ($registro = $resultado->fetch()) {
                $reserva["Habitacion"] = $registro["Habitacion"];
                $reserva["Cliente"] = $registro["Cliente"];
                $reserva["FechaEntrada"] = $registro["FechaEntrada"];
                $reserva["FechaSalida"] = $registro["FechaSalida"];

                $reservas[$registro["id"]] = $reserva;
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
            $pdo = new PDO("mysql:host={$db['admin']['host']};dbname={$db['admin']['db']};charset=utf8", $db['admin']['username'], $db['admin']['password']);
    
            $sql = "SELECT COUNT(*) as count FROM usuarios WHERE APIKEY = :apiKey";
            $stmt = $pdo->prepare($sql);
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
            $db = $db['admin'];
            return new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8", $db['username'], $db['password']);
        } else {
            echo 'Tipo de cuenta NO valido.';
        }
    }


?>