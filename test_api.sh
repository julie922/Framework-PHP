#!/usr/bin/env bash
BASE="http://127.0.0.1:8000/api"
PASS=0; FAIL=0
UNIQUE="test_$(date +%s)"

check() {
  local label="$1" expected="$2" actual="$3"
  if [ "$actual" = "$expected" ]; then
    echo "  ✓ $label"
    PASS=$((PASS+1))
  else
    echo "  ✗ $label — attendu $expected, reçu $actual"
    FAIL=$((FAIL+1))
  fi
}

echo ""
echo "=== AUTH ==="

# Register
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/register" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$UNIQUE@test.com\",\"password\":\"Password123!\",\"firstName\":\"Test\",\"lastName\":\"User\",\"roles\":[\"ROLE_USER\"]}")
check "POST /auth/register" "201" "$STATUS"

# Register duplicate
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/register" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$UNIQUE@test.com\",\"password\":\"Password123!\",\"firstName\":\"Test\",\"lastName\":\"User\",\"roles\":[\"ROLE_USER\"]}")
check "POST /auth/register (email dupliqué → 400)" "400" "$STATUS"

# Login valide
RESP=$(curl -s -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"client@test.com","password":"Password123!"}')
STATUS=$(echo "$RESP" | grep -o '"accessToken"' | wc -l | tr -d ' ')
check "POST /auth/login (retourne accessToken)" "1" "$STATUS"
TOKEN=$(echo "$RESP" | grep -o '"accessToken":"[^"]*"' | cut -d'"' -f4)
REFRESH=$(echo "$RESP" | grep -o '"refreshToken":"[^"]*"' | cut -d'"' -f4)

# Login invalide
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"client@test.com","password":"wrongpassword"}')
check "POST /auth/login (mauvais mdp → 401)" "401" "$STATUS"

# Refresh token
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/refresh" \
  -H "Content-Type: application/json" \
  -d "{\"refreshToken\":\"$REFRESH\"}")
check "POST /auth/refresh" "200" "$STATUS"

echo ""
echo "=== USERS ==="

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/users/me" -H "Authorization: Bearer $TOKEN")
check "GET /users/me" "200" "$STATUS"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/users/me")
check "GET /users/me (sans token → 401)" "401" "$STATUS"

echo ""
echo "=== SERVICES ==="

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/services")
check "GET /services (public, sans token)" "200" "$STATUS"

COUNT=$(curl -s "$BASE/services" | grep -o '"id"' | wc -l | tr -d ' ')
check "GET /services (retourne des résultats)" "$([ "$COUNT" -gt 0 ] && echo 'ok' || echo 'vide')" "ok"

# Provider token
PTOKEN=$(curl -s -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"provider@test.com","password":"Password123!"}' \
  | grep -o '"accessToken":"[^"]*"' | cut -d'"' -f4)

# Créer un service (prestataire)
RESP=$(curl -s -X POST "$BASE/services" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $PTOKEN" \
  -d '{"title":"Service test","description":"Description test","category":"test","price":100}')
STATUS=$(echo "$RESP" | grep -o '"id"' | head -1 | wc -l | tr -d ' ')
check "POST /services (prestataire)" "1" "$STATUS"
SVC_ID=$(echo "$RESP" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/services" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"Service test","description":"x","category":"test","price":100}')
check "POST /services (client → 403)" "403" "$STATUS"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/services/$SVC_ID")
check "GET /services/{id}" "200" "$STATUS"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/services/$SVC_ID" \
  -H "Authorization: Bearer $PTOKEN")
check "DELETE /services/{id} (propriétaire)" "204" "$STATUS"

echo ""
echo "=== DEMANDES ==="

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/demandes" -H "Authorization: Bearer $TOKEN")
check "GET /demandes" "200" "$STATUS"

RESP=$(curl -s -X POST "$BASE/demandes" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"Demande test","description":"Description test","category":"test","budget":500}')
DEM_ID=$(echo "$RESP" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
check "POST /demandes" "$([ -n "$DEM_ID" ] && echo 'ok' || echo 'fail')" "ok"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/demandes/$DEM_ID" \
  -H "Authorization: Bearer $PTOKEN")
check "DELETE /demandes/{id} (non propriétaire → 403)" "403" "$STATUS"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE/demandes/$DEM_ID" \
  -H "Authorization: Bearer $TOKEN")
check "DELETE /demandes/{id} (propriétaire)" "204" "$STATUS"

echo ""
echo "=== PROPOSITIONS ==="

# Recréer une demande pour tester les propositions
DEM_ID=$(curl -s -X POST "$BASE/demandes" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"Pour proposition","description":"test","category":"test","budget":500}' \
  | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

SVC_ID=$(curl -s -X POST "$BASE/services" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $PTOKEN" \
  -d '{"title":"Pour proposition","description":"test","category":"test","price":100}' \
  | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

RESP=$(curl -s -X POST "$BASE/demandes/$DEM_ID/propositions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $PTOKEN" \
  -d "{\"serviceId\":\"$SVC_ID\",\"price\":450,\"message\":\"Je peux faire ce travail rapidement\"}")
PROP_ID=$(echo "$RESP" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
check "POST /demandes/{id}/propositions (prestataire)" "ok" "$([ -n "$PROP_ID" ] && echo ok || echo fail)"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/propositions" -H "Authorization: Bearer $TOKEN")
check "GET /propositions" "200" "$STATUS"

echo ""
echo "=== INTERFACE WEB ==="

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8000/")
check "GET / (accueil)" "200" "$STATUS"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8000/login")
check "GET /login" "200" "$STATUS"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8000/register")
check "GET /register" "200" "$STATUS"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8000/dashboard")
check "GET /dashboard (sans session → redirect)" "302" "$STATUS"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8000/css/style.css")
check "GET /css/style.css" "200" "$STATUS"

echo ""
echo "=============================="
echo "  ✓ $PASS tests réussis"
[ $FAIL -gt 0 ] && echo "  ✗ $FAIL tests échoués"
echo "=============================="
echo ""
