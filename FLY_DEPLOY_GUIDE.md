# Fly.io Deployment Guide for OmniMart

This guide walks you through deploying the OmniMart e-commerce app to **Fly.io** for free.

By deploying via a Docker container on Fly.io, we can host the PHP server and run a persistent SQLite database without needing external databases.

---

## Prerequisites
1. A Fly.io account. If you don't have one, register for free at [fly.io](https://fly.io/).
2. Install the Fly CLI globally on your system using Winget (this ensures compliance with Windows Application Control policies):
   ```powershell
   winget install Fly-io.flyctl
   ```
   *After installing, restart your terminal to make the `fly` command available.*

---

## Step-by-Step Deployment Instructions

Open your PowerShell terminal and run these commands from the root directory of this project:

### 1. Log In to Fly.io
Authenticate the Fly CLI by logging in via your browser:
```powershell
fly auth login
```
*This command will open a web browser tab. Log in or sign up there, and once authorized, return to your terminal.*

---

### 2. Launch the Application Setup
Initiate the app configuration:
```powershell
fly launch
```
*Follow the interactive prompts:*
*   **App Name:** Choose a unique app name (e.g., `omnimart-shop-yourname`) or press Enter for a random one.
*   **Region:** Select a region close to you (e.g., `lax` for Los Angeles, `ams` for Amsterdam).
*   **Postgres Database:** Select **No** (we are using our built-in SQLite database).
*   **Redis Database:** Select **No**.
*   **Deploy now?** Select **No** (we must create our storage volume first).

*This command will generate a `fly.toml` configuration file in your directory.*

---

### 3. Create a Persistent Storage Volume
Since Fly.io machines are ephemeral (they reset files on restart), we must create a persistent storage volume to save the SQLite database:
```powershell
fly volumes create omnimart_data --size 1 --region CHOSEN_REGION
```
*(Replace `CHOSEN_REGION` with the region you selected in step 2, e.g. `lax` or `ams`)*

---

### 4. Mount the Volume in `fly.toml`
Open the newly generated **`fly.toml`** file in your workspace, scroll to the bottom, and add the following lines to mount our storage volume:

```toml
[mounts]
  source = "omnimart_data"
  destination = "/data"
```

---

### 5. Deploy the Application
Deploy your Dockerized PHP container to the cloud:
```powershell
fly deploy
```
*Fly.io will build the image from our `Dockerfile`, mount the storage volume, and launch the service. This might take 1–2 minutes.*

---

### 6. Initialize the Remote Database
Once deployment completes, open your browser and navigate to your new Fly.dev app URL, adding `/init_db.php` to the path:
`https://<your-app-name>.fly.dev/init_db.php`

This will initialize the schema and populate the tables with default products, vendors, and admin credentials. 

> [!WARNING]
> For security, delete the `init_db.php` file from your local workspace and redeploy (`fly deploy`) once the database has been successfully initialized, to prevent unauthorized users from resetting your live store!
