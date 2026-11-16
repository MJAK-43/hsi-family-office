#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

DB_NAME="${1:-hsi_app}"

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "Refus : nom de base invalide." >&2
    exit 2
fi

if [[ ! -r /etc/psa/.psa.shadow ]]; then
    echo "Refus : identifiants administrateur Plesk indisponibles." >&2
    exit 2
fi

export MYSQL_PWD="$(< /etc/psa/.psa.shadow)"

mysql --user=admin --host=localhost --batch --skip-column-names "$DB_NAME" <<'SQL'
SELECT 'fund_accounts_by_currency', currency, COUNT(*)
FROM fund_accounts
GROUP BY currency
ORDER BY currency;

SELECT 'fund_movements_by_currency', currency, COUNT(*)
FROM fund_movements
GROUP BY currency
ORDER BY currency;

SELECT 'contribution_payments_by_currency', currency, COUNT(*)
FROM contribution_payments
GROUP BY currency
ORDER BY currency;

SELECT 'movement_account_currency_mismatch', COUNT(*)
FROM fund_movements AS movement
INNER JOIN fund_accounts AS account ON account.id = movement.fund_account_id
WHERE movement.currency <> account.currency;

SELECT 'account_balance_mismatch', COUNT(*)
FROM fund_accounts AS account
LEFT JOIN (
    SELECT
        fund_account_id,
        ROUND(SUM(CASE
            WHEN status = 'validated' AND direction = 'credit' THEN amount
            WHEN status = 'validated' AND direction = 'debit' THEN -amount
            ELSE 0
        END), 2) AS calculated_balance
    FROM fund_movements
    GROUP BY fund_account_id
) AS totals ON totals.fund_account_id = account.id
WHERE account.current_balance <> COALESCE(totals.calculated_balance, 0);

SELECT 'duplicate_contribution_account_type', COUNT(*)
FROM (
    SELECT contribution_payment_id, fund_account_id, type
    FROM fund_movements
    WHERE contribution_payment_id IS NOT NULL
    GROUP BY contribution_payment_id, fund_account_id, type
    HAVING COUNT(*) > 1
) AS duplicates;

SELECT 'duplicate_project_type', COUNT(*)
FROM (
    SELECT project_id, type
    FROM fund_movements
    WHERE project_id IS NOT NULL
    GROUP BY project_id, type
    HAVING COUNT(*) > 1
) AS duplicates;

SELECT 'duplicate_reference_type', COUNT(*)
FROM (
    SELECT reference, type
    FROM fund_movements
    WHERE reference IS NOT NULL
      AND type IN ('transfer_in', 'transfer_out')
    GROUP BY reference, type
    HAVING COUNT(*) > 1
) AS duplicates;
SQL

unset MYSQL_PWD
