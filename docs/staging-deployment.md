# NEWTIC staging — GitHub Actions deployment

Target (test only): powderblue-dinosaur-538060.hostingersite.com
Hostinger SSH: u880685189@147.79.123.121, port 65002
App root: ~/domains/powderblue-dinosaur-538060.hostingersite.com/newtic
Public root: ~/domains/powderblue-dinosaur-538060.hostingersite.com/public_html
Branch: feat/phase-01-foundation-homepage

The workflow is intentionally **disabled** until the repository variable
`NEWTIC_DEPLOY_ENABLED` is set to `true`. Nothing is published automatically
without the one-time SSH key setup.

Setup from an already authenticated Hostinger SSH shell (password never in chat):

1. Generate a deployment key **on a private trusted device or Cloud Shell**:
   `ssh-keygen -t ed25519 -C newtic-github-actions -f ~/.ssh/newtic-github-actions -N ''`
2. Copy only the generated **public** key into
   `~/.ssh/authorized_keys` on Hostinger:
   `mkdir -p ~/.ssh && chmod 700 ~/.ssh`
   `cat >> ~/.ssh/authorized_keys` (paste the .pub line, then Ctrl+D);
   `chmod 600 ~/.ssh/authorized_keys`.
3. Verify the Hostinger server fingerprint from the Hostinger panel before
   storing its known_hosts line. For a nonstandard SSH port use
   `ssh-keyscan -p 65002 -t ed25519 147.79.123.121` and compare fingerprint
   out-of-band with your known Hostinger SSH host fingerprint.
4. GitHub > Repository > Settings > Secrets and variables > Actions:
   - Secret `NEWTIC_SSH_PRIVATE_KEY`: **entire** private key file content.
   - Secret `NEWTIC_SSH_KNOWN_HOSTS`: verified full bracketed-host known_hosts line.
   - Repository variable `NEWTIC_DEPLOY_ENABLED`: `true`.
5. Trigger one manual run from Actions > Deploy NEWTIC staging > Run workflow.
   Subsequent pushes to the staging branch run this deploy automatically.

Never send private SSH keys, passwords, database credentials or .env in chat.
GitHub Secrets hide values from future workflows; maintainers can rotate keys
and disable deployments by setting NEWTIC_DEPLOY_ENABLED=false.

Safety: verify current staging front-controller shim and isolated DB name; never
deploy to polariskursun.com or put the Laravel application under public_html.
The workflow compiles assets in GitHub Actions and securely transfers only the
build archive, leaving the private Laravel install outside the web root.
