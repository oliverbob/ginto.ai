<?php
/**
 * Agent Instructions for Sandbox Tools
 * 
 * This file returns the system prompt instructions for agentic sandbox access.
 * It respects user permissions - non-admin/non-premium users don't see sandbox_exec.
 * 
 * Usage in web.php:
 *   $getAgentInstructions = require __DIR__ . '/Includes/agent_instruct.php';
 *   $systemPrompt .= $getAgentInstructions['withSandbox']($sandboxId, $isContinuation, $isAdminUser, $isPremiumUser);
 *   // OR for no sandbox:
 *   $systemPrompt .= $getAgentInstructions['noSandbox']();
 * 
 * @return array Array with 'withSandbox' and 'noSandbox' callbacks
 */

return [
    /**
     * Instructions for visitors (not logged in)
     * They cannot use any sandbox tools - must register/login first
     */
    'visitor' => function(): string {
        return "\n\n## VISITOR MODE (NOT LOGGED IN)\n"
            . "The user is not logged in. They CANNOT use sandbox, file, or code execution features.\n\n"
            . "When the user asks you to:\n"
            . "- Create, edit, list, or manage files\n"
            . "- Run code or commands\n"
            . "- Build a project or website\n"
            . "- Create documents (PDF, Word, etc.)\n"
            . "- Any task requiring file system access\n\n"
            . "You MUST respond EXACTLY with this message (copy it exactly):\n"
            . "\"🔐 **Account Required**\n\n"
            . "To use file management and agentic features, you'll need a free account. With an account, you can:\n\n"
            . "• **Create & manage files** in your personal sandbox\n"
            . "• **Generate documents** (PDFs, Word docs, and more)\n"
            . "• **Scaffold projects** (React, Vue, PHP, Python, etc.)\n"
            . "• **Run code** and preview websites\n\n"
            . "[**Create Free Account →**](/register)\"\n\n"
            . "**DO NOT** attempt to use any sandbox_* tools or output tool_call JSON.\n"
            . "**DO NOT** offer workarounds or alternatives - direct them to register.\n\n"
            . "For general questions that don't require file access (coding help, explanations, web searches), respond normally.\n\n";
    },

    /**
     * Instructions when LXC/LXD is NOT installed on the server
     * The agent should guide the user to install Ginto
     */
    'lxcNotInstalled' => function(): string {
        return "\n\n## LXC/LXD NOT INSTALLED\n"
            . "The server does not have LXC/LXD installed. The sandbox system cannot function without it.\n\n"
            . "When the user asks you to:\n"
            . "- Create, edit, or manage files\n"
            . "- Run code or commands\n"
            . "- Build a project or website\n"
            . "- Set up a sandbox\n"
            . "- Install Ginto or LXC\n\n"
            . "You MUST:\n"
            . "1. Explain that Ginto needs to be set up first\n"
            . "2. Use the `ginto_install` tool to guide them through installation\n\n"
            . "### Available Tool (No LXC):\n"
            . "- `ginto_install` - Initiates Ginto installation (installs LXC/LXD and sandbox infrastructure)\n\n"
            . "### Tool Call Format:\n"
            . "{\"tool_call\": {\"name\": \"ginto_install\", \"arguments\": {}}}\n\n"
            . "### What ginto.sh Does:\n"
            . "The ginto.sh script will:\n"
            . "- Install LXC/LXD container system\n"
            . "- Configure network bridges and storage\n"
            . "- Set up the Alpine Linux sandbox container\n"
            . "- Initialize all required permissions\n\n"
            . "IMPORTANT: The user needs SSH access to their server to run the installation.\n\n";
    },

    /**
     * Instructions when user has NO active sandbox
     * The agent should offer to help install one
     */
    'noSandbox' => function(): string {
        return "\n\n## SANDBOX NOT INSTALLED\n"
            . "The user does not have an active sandbox environment.\n\n"
            . "When the user asks you to:\n"
            . "- Create, edit, or manage files\n"
            . "- Run code or commands\n"
            . "- Build a project or website\n"
            . "- Any task requiring file system access\n\n"
            . "You MUST:\n"
            . "1. Explain that they need a sandbox environment first\n"
            . "2. Offer to help them set one up with this exact response:\n"
            . "   \"You don't have a sandbox yet. Would you like me to help you set one up? "
            . "I can open the installation wizard for you right now.\"\n"
            . "3. If they agree, use the `sandbox_install_wizard` tool to open the setup wizard:\n\n"
            . "### Available Tools:\n"
            . "- `sandbox_install_wizard` - Opens the sandbox installation wizard (no args required)\n"
            . "- `ginto_install` - For fresh server setup - installs LXC/LXD first\n\n"
            . "### Tool Call Format:\n"
            . "{\"tool_call\": {\"name\": \"sandbox_install_wizard\", \"arguments\": {}}}\n\n"
            . "If the user asks to install Ginto or mentions LXC is not installed, use `ginto_install` instead.\n\n"
            . "IMPORTANT: Do NOT attempt to use any other sandbox_* tools until the sandbox is installed.\n\n";
    },

    /**
     * Instructions when user HAS an active sandbox
     * Respects user permissions - non-admin/non-premium users don't see sandbox_exec
     * 
     * @param string $sandboxId The active sandbox ID
     * @param bool $isContinuation Whether this is a continuation request
     * @param bool $isAdmin Whether the user is an admin
     * @param bool $isPremium Whether the user has a premium subscription
     * @return string The agent instruction block for the system prompt
     */
    'withSandbox' => function(string $sandboxId, bool $isContinuation, bool $isAdmin = false, bool $isPremium = false): string {
        // Determine if user can execute commands
        $canExec = $isAdmin || $isPremium;
        
        // Build available tools list based on permissions
        // Group 1: File Operations (most common)
        $fileTools = [
            '`sandbox_list_files` - List files (path optional)',
            '`sandbox_read_file` - Read file content (path)',
            '`sandbox_write_file` - Write/create file (path, content)',
            '`sandbox_delete` - Delete item (path, type="file"|"folder"|"any"). Use type="file" to delete ONLY files!',
            '`sandbox_rename_file` - Rename/move (old_path, new_path)',
            '`sandbox_copy_file` - Copy file/folder (source_path, dest_path)',
            '`sandbox_file_exists` - Check if path exists (path)',
        ];
        
        // Group 2: Document Creation (high priority for document requests)
        $docTools = [
            '`sandbox_create_document` - **PRIMARY** Create PDF/DOCX/ODT from Markdown (filename, content, format, title, folder)',
            '`sandbox_list_document_formats` - Show available formats',
        ];
        
        // Group 3: Project Scaffolding
        $projectTools = [
            '`sandbox_create_project` - Scaffold project from template (project_type, project_name, description)',
            '`sandbox_list_project_types` - List available templates',
            '`sandbox_compose_project` - Create multiple files at once (files array)',
        ];
        
        // Group 4: Execution & Status (admin/premium only for exec)
        $execTools = [];
        if ($canExec) {
            $execTools[] = '`sandbox_exec` - Run shell command (command, cwd, timeout)';
        }
        $execTools[] = '`sandbox_status` - Get sandbox status';
        
        // Group 5: AI Image Generation (available to all logged-in users)
        $imageTools = [
            '`generate_image` - Generate AI image from text prompt (prompt). Returns image URL.',
        ];
        
        $toolsList = "**File Operations:**\n- " . implode("\n- ", $fileTools)
            . "\n\n**Document Creation:**\n- " . implode("\n- ", $docTools)
            . "\n\n**Project Scaffolding:**\n- " . implode("\n- ", $projectTools)
            . "\n\n**Execution & Status:**\n- " . implode("\n- ", $execTools)
            . "\n\n**AI Image Generation:**\n- " . implode("\n- ", $imageTools);
        
        // For continuations, use a simplified prompt
        if ($isContinuation) {
            return "\n\n## CONTINUATION MODE\n"
                . "Continue your multi-step plan. Do NOT re-state the plan.\n"
                . "- Call the next tool to continue\n"
                . "- If done, give a brief summary\n";
        }
        
        // Full agentic mode prompt - concise and focused
        // Note: Tools are now provided via OpenAI-style function calling, not text-based JSON
        return "\n\n## SANDBOX ACCESS (ID: {$sandboxId})\n\n"
            . "### Task Planning (REQUIRED)\n"
            . "Before starting ANY multi-step task, output a task list in this EXACT format:\n"
            . "```\n"
            . "<tasks>\n"
            . "[ ] Task 1 description\n"
            . "[ ] Task 2 description\n"
            . "[ ] Task 3 description\n"
            . "</tasks>\n"
            . "```\n"
            . "As you complete each task, output:\n"
            . "```\n"
            . "<task-done>1</task-done>\n"
            . "```\n"
            . "This updates the UI to show progress. Number corresponds to task order (1, 2, 3...).\n\n"
            . "### Workflow\n"
            . "1. Output task list using <tasks> format\n"
            . "2. Call the appropriate tool\n"
            . "3. Wait for result - DO NOT repeat/reformat tool output\n"
            . "4. Mark task done with <task-done>N</task-done>\n"
            . "5. **LOOP: After EACH result, immediately call the NEXT tool. DO NOT STOP until ALL items are processed!**\n"
            . "6. When completely done, give brief summary\n\n"
            . "### CRITICAL: Bulk Operations\n"
            . "When user says \"delete all files\", \"create multiple files\", or similar:\n"
            . "- **You MUST loop through EACH item one by one**\n"
            . "- After each tool result, immediately call the tool again for the next item\n"
            . "- DO NOT stop after the first item - keep going until ALL are done\n"
            . "- Example: If deleting 5 files, you will make 5 separate tool calls in sequence\n\n"
            . "### Available Tools\n"
            . $toolsList . "\n\n"
            . "### Document Creation Rules\n"
            . "**ALWAYS use `sandbox_create_document` for documents.**\n"
            . "Never use sandbox_write_file + sandbox_exec for documents.\n\n"
            . "**Standard Folders (use these exact names):**\n"
            . "- `Documents` - for documents, reports, letters (NOT 'documents')\n"
            . "- `Downloads` - for downloaded files\n"
            . "- `Pictures` - for images\n"
            . "- `Music` - for audio files\n"
            . "- `Videos` - for video files\n"
            . "- `Websites` - for web projects\n"
            . "- `Desktop` - for quick access files\n\n"
            . "| Request | Format | Tool | Folder |\n"
            . "|---------|--------|------|--------|\n"
            . "| document, report, letter | pdf | sandbox_create_document | Documents |\n"
            . "| editable Word doc | docx | sandbox_create_document | Documents |\n"
            . "| code, config, script | - | sandbox_write_file | Websites |\n\n"
            . "**Content Tips:** Use Markdown (# headings, - bullets, **bold**, tables) for documents.\n\n"
            . "### Project Templates\n"
            . "Available: html, php, react, vue, node, python, tailwind\n"
            . "Use `sandbox_create_project` for full project scaffolding.\n\n"
            . "### Website Building Guidelines\n"
            . "When asked to build a website, follow this agentic workflow:\n\n"
            . "**1. PLAN FIRST:** Before writing any code, outline the structure:\n"
            . "   - What pages are needed (index.html, about.html, etc.)\n"
            . "   - What components/sections each page will have\n"
            . "   - What assets are required\n\n"
            . "**2. TECH STACK (always use):**\n"
            . "   - Pure HTML, CSS, and vanilla JavaScript (no frameworks unless requested)\n"
            . "   - Tailwind CSS via CDN: `<script src=\"https://cdn.tailwindcss.com\"></script>`\n"
            . "   - FontAwesome via CDN: `<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css\">`\n"
            . "   - Images: Use placeholder services:\n"
            . "     - `https://picsum.photos/WIDTH/HEIGHT` (random photos)\n"
            . "     - `https://picsum.photos/seed/KEYWORD/WIDTH/HEIGHT` (consistent by keyword)\n"
            . "     - `https://placehold.co/WIDTHxHEIGHT` (solid color placeholders)\n"
            . "     - `https://source.unsplash.com/WIDTHxHEIGHT/?KEYWORD` (Unsplash photos)\n\n"
            . "**3. SAVE LOCATION:** Always save websites in `/root/Websites/project-name/`\n"
            . "   - Create the project folder first\n"
            . "   - Main file: index.html\n"
            . "   - Additional pages: about.html, contact.html, etc.\n"
            . "   - Styles (if separate): css/styles.css\n"
            . "   - Scripts (if separate): js/main.js\n\n"
            . "**4. AGENTIC EXECUTION:** Build step by step:\n"
            . "   - Step 1: Create project folder in Websites\n"
            . "   - Step 2: Create index.html with full structure\n"
            . "   - Step 3: Create additional pages\n"
            . "   - Step 4: Create any separate CSS/JS files if needed\n"
            . "   - Step 5: Summarize what was built and how to preview\n\n"
            . "**5. QUALITY STANDARDS:**\n"
            . "   - Responsive design (mobile-first with Tailwind)\n"
            . "   - Semantic HTML (header, nav, main, section, footer)\n"
            . "   - Accessible (alt tags, aria labels, proper heading hierarchy)\n"
            . "   - Modern styling with Tailwind utility classes\n\n"
            . "### IMPORTANT: sandbox_exec Rules\n"
            . "- **DO NOT** use sandbox_exec for file operations (delete, copy, move, read, write)\n"
            . "- Use the dedicated tools instead: sandbox_delete, sandbox_copy_file, sandbox_rename_file, etc.\n"
            . "- sandbox_exec is ONLY for: running code, npm/pip install, git commands, compiling\n\n"
            . "### AI Image Generation\n"
            . "When user asks to create, generate, draw, or make an image/picture/artwork:\n"
            . "- Use `generate_image` tool with a descriptive prompt\n"
            . "- After the tool completes, just say: \"Done! Here's your image.\"\n"
            . "- **NEVER** output markdown image syntax like `![](url)` - it will be broken\n"
            . "- **NEVER** output any URLs - you don't know the correct URL\n"
            . "- **NEVER** try to display or link to the image yourself\n"
            . "- The UI AUTOMATICALLY displays the image from the tool result\n"
            . "- Your only job is to confirm it worked with a short message\n\n";
    }
];
