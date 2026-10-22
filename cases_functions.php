<?php
/**
 * Funksione profesionale për menaxhimin dhe arkivimin e lëndëve noteriale
 * @author Valon Sadiku
 * @copyright e-Noteria Platform
 */

require_once 'confidb.php';

/**
 * Shton një lëndë të re në arkiv
 * @param PDO $pdo
 * @param string $case_number Numri unik i lëndës
 * @param string $title Titulli i lëndës
 * @param string $description Përshkrimi i lëndës
 * @param int $client_id ID e klientit
 * @param int $opened_by ID e noterit/stafit që hap lëndën
 * @return int ID e lëndës së re
 */
function shtoLende(PDO $pdo, string $case_number, string $title, string $description, int $client_id, int $opened_by): int {
    $stmt = $pdo->prepare("INSERT INTO cases (case_number, title, description, client_id, opened_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$case_number, $title, $description, $client_id, $opened_by]);
    return (int)$pdo->lastInsertId();
}

/**
 * Shton një dokument të ri në një lëndë ekzistuese
 * @param PDO $pdo
 * @param int $case_id ID e lëndës
 * @param string $file_name Emri i dokumentit
 * @param string $file_path Path relativ në server
 * @param int $uploaded_by ID e përdoruesit që ngarkon
 * @param string $doc_type Tipi i dokumentit (p.sh. kontratë, akt, etj.)
 * @return int ID e dokumentit të ri
 */
function shtoDokumentLende(PDO $pdo, int $case_id, string $file_name, string $file_path, int $uploaded_by, string $doc_type): int {
    $stmt = $pdo->prepare("INSERT INTO case_documents (case_id, file_name, file_path, uploaded_by, doc_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$case_id, $file_name, $file_path, $uploaded_by, $doc_type]);
    return (int)$pdo->lastInsertId();
}

/**
 * Regjistron çdo veprim në audit trail të lëndës
 * @param PDO $pdo
 * @param int $case_id ID e lëndës
 * @param int $user_id ID e përdoruesit
 * @param string $action Veprimi (krijim, ndryshim, ngarkim dokumenti, etj.)
 * @param string $details Detaje të veprimit
 * @return void
 */
function regjistroAudit(PDO $pdo, int $case_id, int $user_id, string $action, string $details): void {
    $stmt = $pdo->prepare("INSERT INTO case_audit_trail (case_id, user_id, action, details) VALUES (?, ?, ?, ?)");
    $stmt->execute([$case_id, $user_id, $action, $details]);
}

/**
 * Kërkon lëndë sipas numrit ose klientit
 * @param PDO $pdo
 * @param string $search_case_number
 * @param int $search_client_id
 * @return array Lista e lëndëve që përputhen
 */
function kerkoLende(PDO $pdo, string $search_case_number, int $search_client_id): array {
    $stmt = $pdo->prepare("SELECT * FROM cases WHERE case_number = ? OR client_id = ?");
    $stmt->execute([$search_case_number, $search_client_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Liston të gjitha dokumentet aktive të një lënde
 * @param PDO $pdo
 * @param int $case_id
 * @return array Lista e dokumenteve
 */
function listDokumenteLende(PDO $pdo, int $case_id): array {
    $stmt = $pdo->prepare("SELECT * FROM case_documents WHERE case_id = ? AND is_active = 1 ORDER BY uploaded_at DESC");
    $stmt->execute([$case_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
