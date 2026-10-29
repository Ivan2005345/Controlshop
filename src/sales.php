<?php
/**
 * Registro de una venta completa (todas las líneas del carrito).
 * Todo ocurre dentro de UNA transacción: o se registra todo, o no se registra nada.
 */

/**
 * @param array<int,int> $carrito  [id_producto => cantidad]
 * @return float total de la venta
 * @throws RuntimeException con un mensaje apto para mostrar al usuario
 */
function registrar_venta(int $negocioId, int $usuarioId, array $carrito): float
{
    if (!$carrito) {
        throw new RuntimeException('El carrito está vacío.');
    }

    $pdo = db();
    $total = 0.0;
    $pdo->beginTransaction();

    try {
        foreach ($carrito as $productoId => $cantidad) {
            $productoId = (int) $productoId;
            $cantidad = (int) $cantidad;

            // Descuenta el stock solo si alcanza. Al ser una sola instrucción SQL es atómica:
            // dos ventas simultáneas no pueden dejar el stock en negativo.
            $upd = $pdo->prepare(
                'UPDATE productos SET stock = stock - :c
                  WHERE id = :id AND negocio_id = :n AND activo = true AND stock >= :c2
              RETURNING precio'
            );
            $upd->execute([':c' => $cantidad, ':id' => $productoId, ':n' => $negocioId, ':c2' => $cantidad]);
            $precio = $upd->fetchColumn();

            if ($precio === false) {
                $info = db_row('SELECT nombre, stock FROM productos WHERE id = :id AND negocio_id = :n', [':id' => $productoId, ':n' => $negocioId]);
                throw new RuntimeException($info
                    ? "No hay stock suficiente de «{$info['nombre']}» (disponible: {$info['stock']})."
                    : 'Un producto del carrito ya no existe.');
            }

            // El precio se toma de la base de datos al cobrar, no de lo que el navegador envió.
            $ins = $pdo->prepare(
                'INSERT INTO ventas (negocio_id, id_producto, id_usuario, cantidad, precio_unitario)
                 VALUES (:n, :p, :u, :c, :pr)'
            );
            $ins->execute([':n' => $negocioId, ':p' => $productoId, ':u' => $usuarioId, ':c' => $cantidad, ':pr' => $precio]);

            $total += $cantidad * (float) $precio;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $total;
}
