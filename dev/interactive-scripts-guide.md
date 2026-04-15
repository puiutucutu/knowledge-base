# Interactive npm Scripts: The Superpowered Terminal Workflow

If your `package.json` is bloated with dozens of scripts (`dev`, `build:web`, `build:server`, `test:e2e`, etc.), typing them out or scrolling through a dense list gets tedious fast.

This guide will show you how to combine two incredibly powerful, universally respected CLI tools—**`jq`** and **`fzf`**—to create a blazing-fast, fuzzy-searchable, interactive menu for your npm scripts.

---

## Why `jq` and `fzf`?

Instead of relying on a dedicated npm package (like `ntl`), we are using standard Unix philosophy: piping small, sharp tools together.

- **`jq` (Command-line JSON processor):** It reads your `package.json` and cleanly extracts just the script names.
- **`fzf` (Fuzzy Finder):** It takes that list, displays it in a full-terminal interactive menu, and lets you filter it instantly by typing partial, "fuzzy" matches.

**The result:** You type `scripts`, start typing `bw`, hit Enter, and `build:web` runs instantly.

---

## Installation

### For Windows (using `winget`)

Run these commands in your PowerShell or Windows Terminal:

```bash
winget install jqlang.jq
winget install junegunn.fzf
```

_Note: You must restart your terminal completely after installation._

### For macOS (using Homebrew)

```bash
brew install jq fzf
```

### For Linux (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install jq fzf
```

---

## Configuration

To make this seamless, we will create an alias in your shell configuration profile.

1. Open your terminal profile:
   - **Bash (Git Bash / WSL / Linux):** `~/.bashrc` or `~/.bash_profile`
   - **Zsh (macOS default):** `~/.zshrc`

2. Add this line to the bottom of the file:

   ```bash
   alias scripts='npm run $(jq -r ".scripts | keys[]" package.json | fzf)'
   ```

3. Reload your profile to apply the changes immediately:
   - Bash: `source ~/.bashrc`
   - Zsh: `source ~/.zshrc`

### How the command works under the hood:

Let's break down the alias line:

```bash
alias scripts='npm run $(jq -r ".scripts | keys[]" package.json | fzf)'
```

**Step 1: Extract the script names from `package.json`**

```bash
jq -r ".scripts | keys[]" package.json
```

This `jq` command does three things in sequence:

1. `".scripts"` — Navigates to the `"scripts"` object inside `package.json`.
2. `keys[]` — Extracts only the keys (e.g., `"dev"`, `"build"`, `"test"`), throwing away the actual commands.
3. `-r` (raw output) — Removes the JSON string quotes around each name, producing clean one-per-line text.

The result is a clean list of script names, one per line:

```bash
dev
build
build:web
build:server
test
test:web
generate:words
...
```

**Step 2: Pipe the list into the fuzzy finder**

```bash
| fzf
```

The `|` symbol (pipe) takes the output from `jq` and feeds it directly into `fzf`. `fzf` renders this list as an interactive, full-screen menu in your terminal. It waits for you to select an item or type a filter.

**Step 3: Run the selected script**

```bash
npm run $(...)
```

When you press `Enter` on your selection, `fzf` outputs that script name. The `$(...)` syntax (called command substitution) captures that output and substitutes it back into the command. `npm run` then executes it.

**The full pipeline visualized:**

```bash
package.json
    │
    ▼
jq -r ".scripts | keys[]"
    │
    ▼  (list of script names)
fzf  ◄── interactive selection / fuzzy search
    │
    ▼  (selected script name, e.g. "dev")
npm run $(...)
    │
    ▼
"pnpm run dev" executes!
```

---

## Usage

1. Navigate to any JavaScript/TypeScript project directory containing a `package.json`.
2. Type the command:
   ```bash
   scripts
   ```
3. A list of all your available scripts will appear at the bottom of your terminal.
4. **Navigate:** Use the `Up` and `Down` arrow keys.
5. **Fuzzy Search:** Just start typing! If you want to run `generate:words`, typing `gw` will instantly filter the list down to that command.
6. **Execute:** Press `Enter` to select and run the script.

---

## Bonus: Other ways to use `fzf`

Now that you have `fzf` installed, you have unlocked a superpower for your terminal.

- **Better History:** In most shells, pressing `CTRL+R` with `fzf` installed will replace the clunky default history search with a beautiful, full-screen fuzzy-searchable menu of your past commands.
- **Kill Processes:** `ps aux | fzf | awk '{print $2}' | xargs kill`
- **Switch Git Branches:** `git branch | fzf | xargs git checkout`
