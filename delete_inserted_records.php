<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

$names = [
    'Margie R. Perez', 'Andrie De Dios', 'Abigail G. Galindo', 'Maria Escote',
    'Antonio Redoma Platino', 'Emma A. Bella Rosa', 'Sergio T. Decastro',
    'Akisha Geil R. Perez', 'Reymundo C. Corona', 'Edelberto N. Ola',
    'Fedirico B. Gasic', 'Reman Y. Dioquino', 'Jennifer C. Cordero',
    'Edwin P. Gonda', 'Dionisia R. Rey', 'Serafio De Guzman', 'Jun B. Cabansag',
    'Danilo D. Ola', 'Warren V. Ulip', 'Josefina M. Castro', 'Joylene A. Villanueva',
    'Virginia F. Furmoal', 'Mark Reo T. Limbo', 'Crispina Villanueva',
    'Pepito D. Alejandra', 'Veronica A. Kalaw', 'Francisca B. Claus',
    'Myrna O. Raton', 'Ariel C. Adeva', 'Alfonso Dimaala', 'Angelito E. Salazar',
    'Benita T. Estrada', 'Juan B. Dela Rosa', 'Genaro Yanes', 'Alberto Pascoa',
    'Treneo N. Mista', 'Felipa Delmundo', 'Urbano G. Dorado', 'Alda Impang Villena',
    'Blas M. Manguiat', 'Annamhel Bueno', 'Estelita N. Mistas', 'Valentine M. Toralba',
    'Felix G. Guinoria', 'Juanita I. Guinoria', 'Melie A. Estabaya',
    'Corazon P. Ale Ale', 'Cerilo Cueto', 'Dolores M. Rustia',
    'Celestino Manilic Caringal', 'Adelaida Dinglasan', 'Ana Loreto P. Gatilo',
    'Catalina M. Catilo', 'Juanito O. Nobelo', 'Ricarda Nobelo', 'Hugo H. Harabe',
    'Rufino H. Labiaga', 'Antonia G. Acosta', 'Federico M. Mateo',
    'Wenelita G. Pasigan', 'Francisca Hernandez', 'Leonisa Mercene Lasio',
    'Gregorio S. Baculo', 'Arceli F. Galvero', 'Siony V. Catapang',
    'Bernarda C. Calangi', 'Juan R. Castillo', 'Angelita D. Artiola',
    'Milandrina B. Reyes', 'Pepito Hernandez', 'Yolanda Magsino',
    'Bartolome C. Laygo', 'Matilde C. Laygo', 'Jose E. Gutierrez',
    'Democrito D. Bueno', 'Lolita M. Gutierrez', 'Leonardo D. Atienza',
    'Jhon Mark O. De Glaro', 'Cosme B. Esguerra', 'Matias D. Dimaala',
    'Mercedes Biado', 'Consuelo Cuevas', 'Aurelio L. Asis', 'June Cortez',
    'Sotero M. Cuevas', 'Lemuel C. Trojo', 'Jennybel T. Hunas',
    'Herminia Tolentino', 'Tomas M. Carpio', 'Nena F. Fabio', 'Lorna S. Garcia',
    'Gelacio D. Garcia', 'Bovena Velena', 'Ruel Mogol', 'Granciano A. Magboo',
    'Margina Garian', 'Roberto S. Ong', 'Romeo B. Gubi', 'Alberto C. Gubi',
    'Angelo R. Basilan', 'Jennifer P. Mercado', 'Jose Abe', 'Teodocia H. Alulod',
    'Juanito Delos Reyes', 'Alberto V. Marciano', 'Joel M. Petallo',
    'Dorotea Ferrer', 'Dolores Madla', 'Mariafe O. Oliverio', 'Domingo De Chavez',
    'Roberto R. Alaraz', 'Rodolfo F. Ferelin', 'Luciano D. Dimaala',
    'Jhon Christian Montero Fornider', 'Nonilon P. Visto', 'Magdalina Benetez',
    'Noel Cortez', 'Eugenia V. Marasigan', 'Mario Hernandez', 'Rodolfo V. Patilano',
    'Loreto C. Cereza Jr.', 'Justina A. Dela Rosa', 'Gloria E. Detera',
    'Florencia S. Mercene', 'Delfin S. Lacsina', 'Charity C. Espina',
    'Buena Saron', 'Joseph Robles', 'Dominga B. Turqueza', 'Leopoldo M. Galaano',
    'Bartolome Dinglasan', 'Landrico P. Gacilo', 'Inosencio T. Gabayno',
    'Henry Hagonoy Lato', 'Ceferino B. Claus', 'Leovigildo Carandang',
    'Baylon Landicho', 'Analyn M. Daragay', 'Oliva K. Asis', 'Manuel S. Perante',
    'Virginia Catamura', 'Juancho M. Aguilar', 'Michael C. Delos Reyes',
    'Jerry E. Tolentino',
];

$placeholders = implode(',', array_fill(0, count($names), '?'));
// Only delete records with no lot assigned (lot_id IS NULL) to be safe
$stmt = $conn->prepare("DELETE FROM deceased_records WHERE full_name IN ($placeholders) AND lot_id IS NULL");
$stmt->execute($names);
$deleted = $stmt->rowCount();

echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Removed <b>$deleted</b> unassigned records.</p>";
echo "<a href='public/burial-records.php' style='font-family:sans-serif;'>Go to Burial Records</a>";
