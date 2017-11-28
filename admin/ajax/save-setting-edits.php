<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        if (count($_POST) == 0) {
            header("Location: $basePath/admin/edit-settings.php?saved");
            die();
        }
        foreach ($_POST as $key => $value) {
            if ($key === 'action') {
                continue;
            }
            $params = [
                $value,
                date("Y-m-d H:i:s")
            ];
            $query = '
                UPDATE Settings SET SettingValue = ?, LastEdited = ? WHERE SettingID = ' . $key;
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        }

        header("Location: $basePath/admin/edit-settings.php?saved");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>