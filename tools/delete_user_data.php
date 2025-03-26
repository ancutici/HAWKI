#!/usr/bin/env php
<?php
/**
 * Dieses Skript löscht sämtliche Daten eines Benutzers in den
 * Tabellen usage_records, passkey_backups, private_user_data,
 * sessions, invitations, ai_conv_msgs, ai_convs, messages, members,
 * rooms (nur leere / nur-assistant-Räume) sowie abschließend den
 * Datensatz aus users selbst.
 *
 * Aufruf (Beispiel):
 *   php delete_user_data.php ancutici
 * 
 * Voraussetzung:
 *   sudo -u www-data -H composer require vlucas/phpdotenv
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ---------- 1) Prüfen, ob ein Benutzername übergeben wurde ----------
if ($argc < 2) {
    echo "Benutzung: php delete_user_data.php <username>\n";
    exit(1);
}

$username = $argv[1];

// ---------- DB-Zugangsdaten ----------
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); 
$dotenv->load();

$dbHost      = $_ENV['DB_HOST'];
$dbPort      = $_ENV['DB_PORT'];
$dbDatabase  = $_ENV['DB_DATABASE'];
$dbUser      = $_ENV['DB_USERNAME'];
$dbPass      = $_ENV['DB_PASSWORD'];
$dbCharset   = $_ENV['DB_CHARSET'] ?? 'utf8';
$dbCollation = $_ENV['DB_COLLATION'] ?? 'utf8_unicode_ci';

// ---------- 2) Verbindung aufbauen ----------
$dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbDatabase;charset=$dbCharset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

    // ---------- 3) User-ID ermitteln ----------
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :uname");
    $stmt->execute(['uname' => $username]);
    $uid = $stmt->fetchColumn();

    if (!$uid) {
        echo "Kein Benutzer mit Username '{$username}' gefunden.\n";
        exit(1);
    }

    // ---------- 4) Transaktion starten ----------
    $pdo->beginTransaction();

    // -------------------------------
    // 2) Direkte User-Referenzen löschen
    // -------------------------------

    // 2.1 usage_records
    $stmt = $pdo->prepare("DELETE FROM usage_records WHERE user_id = :uid");
    $stmt->execute(['uid' => $uid]);

    // 2.2 passkey_backups
    $stmt = $pdo->prepare("DELETE FROM passkey_backups WHERE username = :uname");
    $stmt->execute(['uname' => $username]);

    // 2.3 private_user_data
    $stmt = $pdo->prepare("DELETE FROM private_user_data WHERE user_id = :uid");
    $stmt->execute(['uid' => $uid]);

    // 2.4 sessions
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = :uid");
    $stmt->execute(['uid' => $uid]);

    // 2.5 invitations
    $stmt = $pdo->prepare("DELETE FROM invitations WHERE username = :uname");
    $stmt->execute(['uname' => $username]);

    // -------------------------------
    // 3) AI-Chat-Daten
    // -------------------------------

    // 3.1 AI-Chat-Messages
    $stmt = $pdo->prepare("DELETE FROM ai_conv_msgs WHERE user_id = :uid");
    $stmt->execute(['uid' => $uid]);

    // 3.2 AI-Chat-Conversations
    $stmt = $pdo->prepare("DELETE FROM ai_convs WHERE user_id = :uid");
    $stmt->execute(['uid' => $uid]);

    // -------------------------------
    // 4) Nachrichten und Mitgliedschaften des Users in Räumen löschen
    // -------------------------------

    // 4.1 messages (über Tabelle members verknüpft)
    $sqlDelMsgs = "
        DELETE FROM messages
         WHERE member_id IN (
           SELECT id FROM members WHERE user_id = :uid
         )
    ";
    $stmt = $pdo->prepare($sqlDelMsgs);
    $stmt->execute(['uid' => $uid]);

    // 4.2 members
    $stmt = $pdo->prepare("DELETE FROM members WHERE user_id = :uid");
    $stmt->execute(['uid' => $uid]);

    // -------------------------------
    // 5) Räume löschen, die keine Mitglieder oder nur solche mit Rolle 'assistant' haben
    //    (keine Temp-Table, sondern per SELECT + Loop)
    // -------------------------------
    $sqlRooms = "
        SELECT r.id
          FROM rooms r
          LEFT JOIN members m ON m.room_id = r.id
         GROUP BY r.id
        HAVING COUNT(CASE WHEN m.role <> 'assistant' THEN 1 END) = 0
    ";
    $roomsStmt = $pdo->query($sqlRooms);
    $roomsToDelete = $roomsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Eventuelle Raum-Avatarbilder ermitteln zum späteren Löschen
    if (!empty($roomsToDelete)) {
        $placeholders = implode(',', array_fill(0, count($roomsToDelete), '?'));
        $stmt = $pdo->prepare("SELECT room_icon FROM rooms WHERE id IN ($placeholders)");
        $stmt->execute($roomsToDelete);
        $roomIcons = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }    

    foreach ($roomsToDelete as $roomId) {
        // 5.2: Assistant-Nachrichten in diesen Räumen löschen
        $delMessages = $pdo->prepare("DELETE FROM messages WHERE room_id = :rid");
        $delMessages->execute(['rid' => $roomId]);

        // 5.3: Mitglieder-Einträge entfernen
        $delMembers = $pdo->prepare("DELETE FROM members WHERE room_id = :rid");
        $delMembers->execute(['rid' => $roomId]);

        // 5.4: Räume selbst löschen
        $delRooms = $pdo->prepare("DELETE FROM rooms WHERE id = :rid");
        $delRooms->execute(['rid' => $roomId]);
    }

    // -------------------------------
    // 6) User selbst entfernen
    // -------------------------------

    // 6.1 Eventuelles User-Avatarbild ermitteln
    $stmt = $pdo->prepare("SELECT avatar_id FROM users WHERE username = :uname");
    $stmt->execute(['uname' => $username]);
    $userAvatar = $stmt->fetchColumn();

    // 6.2 User selbst aus DB entfernen
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :uid");
    $stmt->execute(['uid' => $uid]);

    // ---------- 5) Transaktion abschließen ----------
    $pdo->commit();

    echo "Die Daten für Benutzer '{$username}' wurden erfolgreich gelöscht.\n";

} catch (Exception $e) {
    // Falls etwas schiefläuft: Rollback
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FEHLER bei der Löschung: " . $e->getMessage() . "\n";
    exit(1);
}

// Eventuelle Avatarbilder löschen
if (!empty($userAvatar)) {
    $userAvatarPath = __DIR__ . "/../storage/app/public/profile_avatars/" . $userAvatar;
    if (file_exists($userAvatarPath)) {
        if (unlink($userAvatarPath)) {
            echo "User-Avatarbild '{$userAvatar}' wurde gelöscht.\n";
        } else {
            echo "WARNUNG: User-Avatarbild '{$userAvatar}' konnte nicht gelöscht werden.\n";
        }
    }
}

// Eventuelle Raum-Avatarbilder löschen
if (!empty($roomsToDelete)) {
    foreach ($roomIcons as $icon) {
        if (!empty($icon)) {
            $roomAvatarPath = __DIR__ . "/../storage/app/public/room_avatars/" . $icon;
            if (file_exists($roomAvatarPath)) {
                if (unlink($roomAvatarPath)) {
                    echo "Raum-Avatarbild '{$icon}' wurde gelöscht.\n";
                } else {
                    echo "WARNUNG: Raum-Avatarbild '{$icon}' konnte nicht gelöscht werden.\n";
                }
            }
        }
    }	
}