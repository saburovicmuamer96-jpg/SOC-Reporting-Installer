#!/bin/bash
# SOC Reporting System - Hostinger Database Setup
# This script sets up all 5 databases after they've been created in hPanel

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}SOC Database Setup${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Prompt for database credentials
echo -e "${YELLOW}Enter MySQL credentials from Hostinger hPanel:${NC}"
read -p "Database User (e.g., u294365219_soc): " DB_USER
read -sp "Database Password: " DB_PASS
echo ""

# Database names
SYSTEM_DB="u294365219_soc_system"
MACHINE_DBprefix="u294365219_soc_m"

# Test connection
echo -e "${YELLOW}Testing database connection...${NC}"
if mysql -u "$DB_USER" -p"$DB_PASS" -e "SHOW DATABASES;" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Connection successful${NC}"
else
    echo -e "${RED}✗ Connection failed. Please check your credentials.${NC}"
    exit 1
fi

# Setup system database
echo -e "${YELLOW}Setting up system database...${NC}"
cd ~/domains/tan-frog-807588.hostingersite.com/public_html

if [ -f "sql/system.sql" ]; then
    mysql -u "$DB_USER" -p"$DB_PASS" "$SYSTEM_DB" < sql/system.sql
    echo -e "${GREEN}✓ System database configured${NC}"
else
    echo -e "${RED}✗ sql/system.sql not found${NC}"
    exit 1
fi

# Setup machine databases
for i in 1 2 3 4; do
    MACHINE_DB="${MACHINE_DB_PREFIX}${i}"
    echo -e "${YELLOW}Setting up machine database $i...${NC}"

    if [ -f "sql/machine_template.sql" ]; then
        mysql -u "$DB_USER" -p"$DB_PASS" "$MACHINE_DB" < sql/machine_template.sql
        mysql -u "$DB_USER" -p"$DB_PASS" "$MACHINE_DB" < sql/migration_add_deleted_reports.sql
        echo -e "${GREEN}✓ Machine $i database configured${NC}"
    else
        echo -e "${RED}✗ sql/machine_template.sql not found${NC}"
        exit 1
    fi
done

# Setup threat_logs table
echo -e "${YELLOW}Setting up threat logs...${NC}"
if [ -f "sql/threat_logs.sql" ]; then
    mysql -u "$DB_USER" -p"$DB_PASS" "$SYSTEM_DB" < sql/threat_logs.sql
    echo -e "${GREEN}✓ Threat logs table created${NC}"
fi

# Update config file with credentials
echo -e "${YELLOW}Updating configuration file...${NC}"
sed -i "s/DB_USER', '[^']*'/DB_USER', '$DB_USER'/" config/database.php
sed -i "s/DB_PASS', '[^']*'/DB_PASS', '$DB_PASS'/" config/database.php
echo -e "${GREEN}✓ Configuration updated${NC}"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✓ Database Setup Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${GREEN}URL:${NC} https://tan-frog-807588.hostingersite.com"
echo -e "${GREEN}Default login:${NC} admin / Admin@SOC2024"
echo ""
echo -e "${YELLOW}Next step: Run the initial setup${NC}"
echo "$ php setup.php"
echo ""
