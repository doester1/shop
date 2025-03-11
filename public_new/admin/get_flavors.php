<?php
require '../includes/db.php';

if (isset($_POST['category_id'])) {
    $category_id = intval($_POST['category_id']);
    $query_flavors = "SELECT smak, ilosc FROM produkty WHERE category_id = $category_id AND ilosc > 0";
    $flavors_result = mysqli_query($conn, $query_flavors);

    if (mysqli_num_rows($flavors_result) > 0) {
        echo '<option value="" disabled selected>Wybierz smak</option>';
        while ($flavor = mysqli_fetch_assoc($flavors_result)) {
            echo '<option value="' . $flavor['smak'] . '">' . $flavor['smak'] . ' (' . $flavor['ilosc'] . ')</option>';
        }
    } else {
        echo '<option value="">Brak dostępnych smaków</option>';
    }
}
?>
