<?php
declare(strict_types=1);

namespace App;

use PDO;

class TaskController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * GET /tasks – list all tasks
     */
    public function list(array $params): void
    {
        $stmt = $this->db->query('SELECT id, title, completed FROM tasks ORDER BY id DESC');
        $tasks = $stmt->fetchAll();
        echo json_encode($tasks);
    }

    /**
     * GET /tasks/{id} – show a single task
     */
    public function show(array $params): void
    {
        $id = (int)$params['id'];
        $stmt = $this->db->prepare('SELECT id, title, completed FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $task = $stmt->fetch();
        if ($task) {
            echo json_encode($task);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
        }
    }

    /**
     * POST /tasks – create a new task
     * Expected JSON body: {"title": "Buy milk", "completed": false}
     */
    public function create(array $params): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['title'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Title is required']);
            return;
        }
        $title = trim($input['title']);
        $completed = $input['completed'] ?? false;

        $stmt = $this->db->prepare('INSERT INTO tasks (title, completed) VALUES (:title, :completed)');
        $stmt->execute([
            'title'     => $title,
            'completed' => $completed ? 1 : 0,
        ]);
        $newId = (int)$this->db->lastInsertId();
        http_response_code(201);
        echo json_encode(['id' => $newId, 'title' => $title, 'completed' => $completed]);
    }

    /**
     * PUT /tasks/{id} – update an existing task
     * Expected JSON body can contain "title" and/or "completed"
     */
    public function update(array $params): void
    {
        $id = (int)$params['id'];
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON payload']);
            return;
        }

        // Build dynamic query based on supplied fields
        $fields = [];
        $values = ['id' => $id];
        if (isset($input['title'])) {
            $fields[] = 'title = :title';
            $values['title'] = trim($input['title']);
        }
        if (isset($input['completed'])) {
            $fields[] = 'completed = :completed';
            $values['completed'] = $input['completed'] ? 1 : 0;
        }
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }

        $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found or no change']);
            return;
        }
        echo json_encode(['message' => 'Task updated']);
    }

    /**
     * DELETE /tasks/{id} – delete a task
     */
    public function delete(array $params): void
    {
        $id = (int)$params['id'];
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            return;
        }
        echo json_encode(['message' => 'Task deleted']);
    }
}
?>
