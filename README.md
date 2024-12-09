<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p> <p align="center"> <a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a> <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a> <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a> <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a> </p>

# About This Project
This is a Laravel-based application built to handle simple SaaS application that allows users to upload an image, and then the application will generate variations of that image using a generative AI API. The app should provide the user with a dashboard to view, manage, and download the generated images.

# Installation
### Prerequisites
##### PHP 8.0+
##### Composer
##### Database: MySQL

##  Steps to Install
### Clone the Repository

##### git clone https://github.com/sagar-dev-work/image_test_backend.git
##### cd image_test_backend

## Install Dependencies

##### composer install
##### Configure Environment Copy .env.example to .env and update the necessary environment variables:


##### cp .env.example .env

### Generate Application Key
##### php artisan key:generate


### Set Up Database
##### Create a new database.
##### Update .env with database credentials.

##### Run migrations:

##### php artisan migrate

##### php artisan make:seeder UserSeeder

## Run the Application Start the server:
##### php artisan serve
##### Access your application at: http://127.0.0.1:8000

## Run command for storage link:
#### php artisan storage:link

## Features

##### Authentication: Secure login and registration.

##### Database Management: Leveraging Eloquent ORM for database interactions.

##### Background Jobs: Process-heavy tasks handled via Laravel Queues.
##### API Endpoints: For seamless integration with other platforms.