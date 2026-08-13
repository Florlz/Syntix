# Alacritty Smoked Obsidian Theme Design

**Status:** Approved design
**Date:** 2026-08-13

## Goal

Make the installed Windows Alacritty terminal feel like a polished, PewDiePie-inspired streamer terminal while keeping it comfortable for daily coding. The visual direction is **Brofist Crimson** with the **Smoked Obsidian** background variant.

## Scope

The change is limited to Alacritty and its required font installation:

- Install the JetBrains Mono font family for the current Windows user or machine, using a trusted Windows package source.
- Create or update the Windows Alacritty TOML configuration at `%APPDATA%\alacritty\alacritty.toml`.
- Preserve any existing Alacritty settings by making a timestamped backup before replacing an existing config.
- Do not edit the PowerShell profile, shell aliases, prompt, environment variables, or the Syntix application.

The repository currently has no Alacritty configuration and has extensive unrelated working-tree changes. Those changes are out of scope and must remain untouched.

## Visual design

### Window

- Use a near-black cool obsidian background: `#0B0D11`.
- Use restrained translucency at approximately 95% opacity so text remains clear.
- Retain normal window decorations and controls.
- Add comfortable, symmetric window padding without forcing a window size or startup mode.
- Do not add a custom shell title or PowerShell-specific branding.

### Typography and cursor

- Use `JetBrains Mono`, regular style, at a comfortable daily-driver size around 11-12 pt.
- Keep line spacing neutral and enable built-in box drawing support for prompt glyphs and terminal UI.
- Use a crisp red cursor with a visible but restrained shape; keep cursor blinking behavior conventional.

### Palette

The palette uses cool light text, crimson action colors, and warm gold highlights:

- Primary foreground: cool off-white, approximately `#E9EDF1`.
- Normal red and bright red: crimson shades around `#D9273A` and `#FF3045`.
- Yellow: muted warm gold around `#D8A33E`.
- Secondary colors: subdued green, blue, magenta, cyan, and gray chosen to remain readable against obsidian.
- Selection: a cool slate selection background with light foreground text.

The theme is inspired by the requested color mood and does not add third-party logos, images, or shell prompt changes.

## Configuration behavior

The resulting TOML must use current Alacritty configuration sections and valid values for the installed Alacritty release. Existing user settings that are not part of the visual scope should be retained where practical; if no config exists, the new file should contain only the focused daily-driver settings.

If the installed release does not support a proposed visual setting on Windows, omit that setting rather than causing a startup error. Opacity is required; blur is optional and should only be enabled if the installed build explicitly supports it.

## Safety and recovery

- Resolve the exact config path before writing.
- If a config exists, copy it to the same directory with a timestamped `.bak` suffix before editing.
- Do not overwrite PowerShell profile files.
- Do not modify files in the Syntix repository as part of implementation.
- If font installation is unavailable through the trusted package source, stop before changing the config and report the installation blocker.

## Validation

After implementation:

1. Confirm JetBrains Mono is installed and discoverable by Windows.
2. Confirm the Alacritty TOML exists at the resolved config path and parses without invalid keys or values.
3. Launch Alacritty using the config and verify that it opens with the Smoked Obsidian background, translucency, JetBrains Mono, crimson/gold palette, padding, and cursor styling.
4. Confirm PowerShell behavior and prompt text are unchanged.
5. Report the exact config path and any backup path created.
