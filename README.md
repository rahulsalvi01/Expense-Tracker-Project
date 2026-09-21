# Full-Stack Expense Tracker (Web to C Integration)

A lightweight, local web application that tracks personal expenses. This project demonstrates system integration by using a modern web frontend to trigger a compiled C program for backend calculations.

Tech Stack:

Frontend: HTML5, CSS3, Bootstrap 5, jQuery

Backend Bridge: PHP (Handling AJAX requests and file I/O)

Calculation Engine: C (Compiled binary for data processing)

Storage: File-based (CSV), no database required

How It Works:
The UI sends asynchronous requests via jQuery to a PHP server. The PHP script securely logs the data to a CSV file and executes a compiled C binary (calc.exe) via shell commands to compute and return the exact total spent.

Want to check this website : https://my-fintrack.infinityfree.io/ ...
