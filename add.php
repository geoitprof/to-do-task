<?php
include("auth.php");
requireLogin();

include("db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task = $conn->real_escape_string($_POST['task']);
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $due_at = !empty($_POST['due_at']) ? "'" . $conn->real_escape_string($_POST['due_at']) . "'" : "NULL";
    $priority = $conn->real_escape_string($_POST['priority'] ?? 'medium');
    $category = $conn->real_escape_string($_POST['category'] ?? 'General');
    $estimated_time = intval($_POST['estimated_time'] ?? 0);
    $tags = $conn->real_escape_string($_POST['tags'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : "NULL";
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $recurrence_pattern = $conn->real_escape_string($_POST['recurrence_pattern'] ?? '');
    $user_id = getCurrentUserId();
    
    // Handle file attachments
    $attachments = [];
    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            if (!empty($tmp_name)) {
                $filename = time() . '_' . $_FILES['attachments']['name'][$key];
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($tmp_name, $filepath)) {
                    $attachments[] = $filename;
                }
            }
        }
    }
    $attachments_json = !empty($attachments) ? "'" . json_encode($attachments) . "'" : "NULL";
    
    $sql = "INSERT INTO tasks (task, description, due_at, priority, category, estimated_time, tags, parent_id, is_recurring, recurrence_pattern, attachments, user_id, created_at) 
            VALUES ('$task', '$description', $due_at, '$priority', '$category', $estimated_time, '$tags', $parent_id, $is_recurring, '$recurrence_pattern', $attachments_json, $user_id, NOW())";
    
    if ($conn->query($sql)) {
        $task_id = $conn->insert_id;
        
        if (isset($_POST['from_template'])) {
            echo json_encode(['success' => true, 'id' => $task_id, 'message' => 'Task created from template']);
        } else if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
            echo json_encode(['success' => true, 'id' => $task_id, 'message' => 'Task added successfully!']);
            exit();
        } else {
            header("Location: index.php");
            exit();
        }
    } else {
        $errorMsg = $conn->error;
        if (isset($_POST['from_template'])) {
            echo json_encode(['success' => false, 'error' => $errorMsg]);
        } else if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
            echo json_encode(['success' => false, 'error' => $errorMsg]);
            exit();
        } else {
            echo "Error: " . $errorMsg;
        }
    }
}

// Fetch existing tasks for parent selection (user-specific for non-admins)
$parent_tasks = [];
$user_id = getCurrentUserId();
$user_filter = hasRole('admin') ? "" : "WHERE user_id = $user_id";
$parent_result = $conn->query("SELECT id, task FROM tasks WHERE parent_id IS NULL $user_filter ORDER BY task");
if ($parent_result) {
    while ($row = $parent_result->fetch_assoc()) {
        $parent_tasks[] = $row;
    }
}

// Fetch categories for dropdown (user-specific for non-admins)
$categories = [];
$cat_result = $conn->query("SELECT DISTINCT category FROM tasks WHERE category IS NOT NULL AND category != '' $user_filter ORDER BY category");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Task</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #111 0%, #333 100%);
            min-height: 100vh;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin: 50px auto;
            max-width: 800px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #222;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #000;
            box-shadow: 0 0 0 0.2rem rgba(0,0,0,0.25);
        }
        
        .btn {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #000 0%, #222 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .section-title {
            color: #000;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .template-item {
            background: white;
            border: 1px solid #222;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .template-item:hover {
            border-color: #000;
            transform: translateY(-2px);
        }
        
        .tag-input-container {
            position: relative;
        }
        
        .tag {
            display: inline-block;
            background: #e0e0e0;
            color: #111;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin: 2px;
        }
        
        .file-upload-area {
            border: 2px dashed #222;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #f9fafb;
            transition: all 0.3s ease;
        }
        
        .file-upload-area:hover, .file-upload-area.dragover {
            border-color: #000;
            background: #222;
        }
        
        .recurrence-options {
            display: none;
        }
        
        .recurrence-options.show {
            display: block;
        }
        
        .template-btn {
            background: linear-gradient(135deg, #222 0%, #000 100%);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin: 5px;
            transition: all 0.3s ease;
        }
        
        .template-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-plus-circle text-primary me-2"></i>Add New Task</h2>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to List
                </a>
            </div>

            <!-- Templates Section -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-clipboard-list"></i>
                    Quick Templates
                </div>
                <div id="templates-container">
                    <div class="d-flex gap-2 flex-wrap" id="template-buttons">
                        <button type="button" class="btn template-btn" onclick="applyTemplate('meeting')">
                            <i class="fas fa-users me-1"></i>Meeting
                        </button>
                        <button type="button" class="btn template-btn" onclick="applyTemplate('email')">
                            <i class="fas fa-envelope me-1"></i>Email Task
                        </button>
                        <button type="button" class="btn template-btn" onclick="applyTemplate('coding')">
                            <i class="fas fa-code me-1"></i>Coding Task
                        </button>
                        <button type="button" class="btn template-btn" onclick="applyTemplate('review')">
                            <i class="fas fa-search me-1"></i>Review Task
                        </button>
                        <button type="button" class="btn template-btn" onclick="applyTemplate('research')">
                            <i class="fas fa-book me-1"></i>Research
                        </button>
                    </div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="taskForm">
                <!-- Basic Information -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Basic Information
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Task Title *</label>
                            <input type="text" name="task" class="form-control" placeholder="Enter task title..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low Priority</option>
                                <option value="medium" selected>Medium Priority</option>
                                <option value="high">High Priority</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional task description..."></textarea>
                    </div>
                </div>

                <!-- Scheduling -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Scheduling
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Due Date & Time</label>
                            <input type="datetime-local" name="due_at" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estimated Time (minutes)</label>
                            <input type="number" name="estimated_time" class="form-control" placeholder="0" min="0">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_recurring" id="isRecurring">
                                <label class="form-check-label" for="isRecurring">
                                    Recurring Task
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="recurrence-options" id="recurrenceOptions">
                                <label class="form-label">Recurrence Pattern</label>
                                <select name="recurrence_pattern" class="form-select">
                                    <option value="">Select pattern...</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Organization -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-folder"></i>
                        Organization
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control" placeholder="e.g., Work, Personal, Health..." list="categoriesList">
                            <datalist id="categoriesList">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parent Task</label>
                            <select name="parent_id" class="form-select">
                                <option value="">No parent task</option>
                                <?php foreach ($parent_tasks as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>">
                                        <?php echo htmlspecialchars($parent['task']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <div class="tag-input-container">
                            <input type="text" name="tags" class="form-control" placeholder="Enter tags separated by commas..." id="tagsInput">
                            <div id="tagDisplay" class="mt-2"></div>
                        </div>
                    </div>
                </div>

                <!-- File Attachments -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-paperclip"></i>
                        Attachments
                    </div>
                    
                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                        <h5>Drag & Drop Files Here</h5>
                        <p class="text-muted">or click to browse</p>
                        <input type="file" name="attachments[]" multiple class="d-none" id="fileInput">
                    </div>
                    <div id="fileList" class="mt-3"></div>
                </div>

                <!-- Submit Button -->
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus me-2"></i>Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Template functionality
        const templates = {
            meeting: {
                task: 'Team Meeting',
                description: 'Attend team meeting and take notes',
                priority: 'medium',
                category: 'Work',
                estimated_time: 60,
                tags: 'meeting, team, work'
            },
            email: {
                task: 'Reply to Important Email',
                description: 'Review and respond to important emails',
                priority: 'high',
                category: 'Communication',
                estimated_time: 30,
                tags: 'email, communication, urgent'
            },
            coding: {
                task: 'Code Review',
                description: 'Review code changes and provide feedback',
                priority: 'medium',
                category: 'Development',
                estimated_time: 120,
                tags: 'code, review, development'
            },
            review: {
                task: 'Document Review',
                description: 'Review and approve documents',
                priority: 'medium',
                category: 'Review',
                estimated_time: 45,
                tags: 'review, documentation, approval'
            },
            research: {
                task: 'Research Task',
                description: 'Conduct research on assigned topic',
                priority: 'low',
                category: 'Research',
                estimated_time: 90,
                tags: 'research, analysis, study'
            }
        };

        function applyTemplate(templateName) {
            const template = templates[templateName];
            if (!template) return;

            document.querySelector('input[name="task"]').value = template.task;
            document.querySelector('textarea[name="description"]').value = template.description;
            document.querySelector('select[name="priority"]').value = template.priority;
            document.querySelector('input[name="category"]').value = template.category;
            document.querySelector('input[name="estimated_time"]').value = template.estimated_time;
            document.querySelector('input[name="tags"]').value = template.tags;
            
            updateTagDisplay();
        }

        // Recurring task toggle
        document.getElementById('isRecurring').addEventListener('change', function() {
            const recurrenceOptions = document.getElementById('recurrenceOptions');
            if (this.checked) {
                recurrenceOptions.classList.add('show');
            } else {
                recurrenceOptions.classList.remove('show');
            }
        });

        // Tag display functionality
        function updateTagDisplay() {
            const tagsInput = document.getElementById('tagsInput');
            const tagDisplay = document.getElementById('tagDisplay');
            const tags = tagsInput.value.split(',').map(tag => tag.trim()).filter(tag => tag !== '');
            
            tagDisplay.innerHTML = '';
            tags.forEach(tag => {
                const tagElement = document.createElement('span');
                tagElement.className = 'tag';
                tagElement.textContent = tag;
                tagDisplay.appendChild(tagElement);
            });
        }

        document.getElementById('tagsInput').addEventListener('input', updateTagDisplay);

        // File upload functionality
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');

        fileUploadArea.addEventListener('click', () => fileInput.click());

        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            displayFiles();
        });

        fileInput.addEventListener('change', displayFiles);

        function displayFiles() {
            fileList.innerHTML = '';
            Array.from(fileInput.files).forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'alert alert-info d-flex justify-content-between align-items-center';
                fileItem.innerHTML = `
                    <div>
                        <i class="fas fa-file me-2"></i>
                        <strong>${file.name}</strong>
                        <small class="text-muted ms-2">(${(file.size / 1024).toFixed(1)} KB)</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                fileList.appendChild(fileItem);
            });
        }

        function removeFile(index) {
            const dt = new DataTransfer();
            Array.from(fileInput.files).forEach((file, i) => {
                if (i !== index) dt.items.add(file);
            });
            fileInput.files = dt.files;
            displayFiles();
        }
    </script>
</body>
</html>