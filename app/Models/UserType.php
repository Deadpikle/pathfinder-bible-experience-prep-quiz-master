<?php

namespace App\Models;

use PDO;

class UserType
{
    public int $userTypeID;
    public string $type;
    public string $displayName;

    public function __construct(int $userTypeID, string $type)
    {
        $this->userTypeID = $userTypeID;
        $this->type = $type;
        $this->displayName = '';
    }

    private static function loadUserTypes(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT UserTypeID, Type, DisplayName
            FROM UserTypes
            ' . $whereClause . '
            ORDER BY DisplayName, Type';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $userType = new UserType($row['UserTypeID'], $row['Type']);
            $userType->displayName = $row['DisplayName'];
            $output[] = $userType;
        }
        return $output;
    }

    /** @return array<UserType> */
    public static function loadAllUserTypes(PDO $db): array
    {
        return UserType::loadUserTypes('', [], $db);
    }

    /** @return array<UserType> */
    public static function loadConferenceAdminEditableUserTypes(PDO $db): array
    {
        return UserType::loadUserTypes('WHERE Type <> "WebAdmin" AND Type <> "ConferenceAdmin" ', [], $db);
    }

    public static function loadUserTypeByID(int $userTypeID, PDO $db): ?UserType
    {
        $data = UserType::loadUserTypes(' WHERE UserTypeID = ? ', [ $userTypeID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadUserTypeByName(string $name, PDO $db): ?UserType
    {
        $data = UserType::loadUserTypes(' WHERE Type = ? ', [ $name ], $db);
        return count($data) > 0 ? $data[0] : null;
    }
}
