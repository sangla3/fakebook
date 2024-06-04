<h3>Setup</h3>
<div>
    <div><b>1. Clone the project: </b><br>git clone https://github.com/fedgds/Social-Media-Website-Fakebook</div>
    <div><b>2. Install: </b><br>composer install and npm install</div>
    <div><b>3. Copy .env.example into .env: </b><br>cp .env.example .env</div>
    <div><b>4. Set encryption key: </b><br>php artisan key:generate --ansi</div>
    <div><b>5. Create symbolic link: </b><br>php artisan storage:link</div>
    <div><b>6. Run migrations: </b><br>php artisan migrate</div>
</div>
<h3>Run: </h3>
<div>
    <div>- <b>php artisan serve</b></div>
    <div>- <b>npm run dev</b></div>
</div>
