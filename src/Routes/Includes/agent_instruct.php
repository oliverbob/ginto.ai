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
        $availableTools = [
            '- `sandbox_list_files` - List files in sandbox (args: path optional)',
            '- `sandbox_read_file` - Read a file (args: path)',
            '- `sandbox_write_file` - Write a file (args: path, content)',
        ];
        
        // Only include sandbox_exec for admin/premium users
        if ($canExec) {
            $availableTools[] = '- `sandbox_exec` - Run command (args: command)';
        }
        
        $availableTools[] = '- `sandbox_create_project` - Create project from template (args: project_type, project_name, description)';
        $availableTools[] = '- `sandbox_delete_file` - Delete file/folder (args: path)';
        
        $toolsList = implode("\n", $availableTools);
        
        // For continuations, use a simplified prompt that doesn't ask for a new plan
        if ($isContinuation) {
            return "\n\n## SANDBOX CONTINUATION MODE\n"
                . "You are continuing a multi-step plan. The user message shows completed steps.\n\n"
                . "RULES:\n"
                . "- Do NOT re-state the plan or output a '**Plan:**' section.\n"
                . "- If there is a NEXT step to execute, output ONLY the tool_call JSON.\n"
                . "- If ALL steps are done, provide a brief 1-2 sentence summary.\n"
                . "- NEVER repeat a tool that's listed in COMPLETED STEPS.\n\n"
                . "### Available Tools:\n"
                . $toolsList . "\n\n"
                . "### Tool Call Format:\n"
                . "{\"tool_call\": {\"name\": \"tool_name\", \"arguments\": {\"arg1\": \"value1\"}}}\n";
        }
        
        // Full agentic mode prompt for initial requests
        return "\n\n## SANDBOX FILE ACCESS - AGENTIC MODE\n"
            . "The user has an active sandbox (ID: {$sandboxId}). You have DIRECT ACCESS to their files.\n\n"
            . "### AGENTIC WORKFLOW - CRITICAL\n"
            . "You are an agentic AI that executes multi-step plans. Follow this workflow:\n\n"
            . "1. **Plan**: State your plan in 2-4 bullet points\n"
            . "2. **Execute ONE tool**: Output exactly ONE tool_call JSON per response\n"
            . "3. **Wait for result**: After the tool runs, you'll receive the result\n"
            . "4. **Continue or Summarize**: If more steps remain, execute the next tool. If done, summarize.\n\n"
            . "IMPORTANT: Only output ONE tool_call per response. The system will send you the result and you can then output the next tool call.\n\n"
            . "### Available Tools:\n"
            . $toolsList . "\n\n"
            . "### Tool Call Format:\n"
            . "{\"tool_call\": {\"name\": \"tool_name\", \"arguments\": {\"arg1\": \"value1\"}}}\n\n"
            . "### Example Multi-Step Plan:\n"
            . "User: 'Improve the mall website'\n\n"
            . "Response 1: 'I'll improve the mall website:\n"
            . "• List current files\n"
            . "• Read index.html\n"
            . "• Write improved version\n\n"
            . "{\"tool_call\": {\"name\": \"sandbox_list_files\", \"arguments\": {\"path\": \"mall\"}}}'\n\n"
            . "(After receiving file list, you respond with next step)\n"
            . "Response 2: 'Found the files. Now reading index.html...\n\n"
            . "{\"tool_call\": {\"name\": \"sandbox_read_file\", \"arguments\": {\"path\": \"mall/index.html\"}}}'\n\n"
            . "(And so on until complete, then summarize)\n\n"
            . "### Project Types: html, php, react, vue, node, python, tailwind\n\n";
    }
];
