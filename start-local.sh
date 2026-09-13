#!/bin/bash
# Start XAMPP services required for the Hospital Management System

XAMPP="/Applications/XAMPP/xamppfiles"

echo "Starting Apache..."
sudo "$XAMPP/xampp" startapache 2>/dev/null || "$XAMPP/bin/apachectl" start 2>/dev/null

echo "Starting MySQL..."
sudo "$XAMPP/xampp" startmysql 2>/dev/null || "$XAMPP/bin/mysql.server" start 2>/dev/null

sleep 2

if curl -s -o /dev/null -w "%{http_code}" "http://localhost/hospital/site/login" | grep -q "200"; then
    echo ""
    echo "Hospital app is running!"
    echo ""
    echo "  Admin login:  http://localhost/hospital/site/login"
    echo "  Patient login: http://localhost/hospital/site/userlogin"
    echo "  Front page:   http://localhost/hospital/"
    echo ""
    echo "Database: hospital (user: root, no password)"
else
    echo ""
    echo "Services started. Open XAMPP Manager if the app is not reachable."
    echo "  http://localhost/hospital/"
fi
