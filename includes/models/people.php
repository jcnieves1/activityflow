<?php
declare(strict_types=1);

function list_people(array $filters = []): array
{
    $sql = 'SELECT p.*, d.name AS department_name, m.full_name AS manager_name
            FROM people p
            LEFT JOIN departments d ON d.id = p.department_id
            LEFT JOIN people m ON m.id = p.manager_id
            WHERE 1=1';
    $params = [];

    if (!empty($filters['search'])) {
        $sql .= ' AND (p.full_name LIKE ? OR p.email LIKE ? OR p.job_title LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        array_push($params, $like, $like, $like);
    }
    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $sql .= ' AND p.is_active = ?';
        $params[] = (int)$filters['is_active'];
    }
    if (!empty($filters['department_id'])) {
        $sql .= ' AND p.department_id = ?';
        $params[] = $filters['department_id'];
    }
    $sql .= ' ORDER BY p.full_name ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_person(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM people WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Simple similarity check to warn about likely-duplicate people before insert. */
function find_similar_people(string $name, ?string $email): array
{
    $pdo = db();
    $matches = [];

    if ($email) {
        $stmt = $pdo->prepare('SELECT id, full_name, email FROM people WHERE email = ?');
        $stmt->execute([$email]);
        $matches = array_merge($matches, $stmt->fetchAll());
    }

    $stmt = $pdo->prepare('SELECT id, full_name, email FROM people WHERE full_name LIKE ?');
    $stmt->execute(['%' . $name . '%']);
    foreach ($stmt->fetchAll() as $row) {
        $matches[$row['id']] = $row;
    }

    return array_values($matches);
}

function create_person(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO people (full_name, job_title, department_id, organization, org_role, email, phone, manager_id, is_active, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
    );
    $stmt->execute([
        $data['full_name'],
        $data['job_title'] ?? null,
        $data['department_id'] ?: null,
        $data['organization'] ?? null,
        $data['org_role'] ?? null,
        $data['email'] ?: null,
        $data['phone'] ?: null,
        $data['manager_id'] ?: null,
        $data['notes'] ?? null,
    ]);
    $id = (int)db()->lastInsertId();
    audit_log('person', $id, 'created', null, $data);
    return $id;
}

function update_person(int $id, array $data): void
{
    $before = get_person($id);
    $stmt = db()->prepare(
        'UPDATE people SET full_name=?, job_title=?, department_id=?, organization=?, org_role=?, email=?, phone=?, manager_id=?, notes=? WHERE id=?'
    );
    $stmt->execute([
        $data['full_name'],
        $data['job_title'] ?? null,
        $data['department_id'] ?: null,
        $data['organization'] ?? null,
        $data['org_role'] ?? null,
        $data['email'] ?: null,
        $data['phone'] ?: null,
        $data['manager_id'] ?: null,
        $data['notes'] ?? null,
        $id,
    ]);
    [$old, $new] = diff_fields($before, $data);
    audit_log('person', $id, 'updated', $old, $new);
}

function set_person_active(int $id, bool $active): void
{
    db()->prepare('UPDATE people SET is_active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    audit_log('person', $id, $active ? 'reactivated' : 'deactivated');
}

function department_list(): array
{
    return db()->query('SELECT * FROM departments WHERE is_active = 1 ORDER BY name')->fetchAll();
}
