# Manager Library · api (Laravel)

Backend de la prueba SDD, ficha ADSO 3239188. Implementa el spec de `test-manager-library-3239188-docs` (`spec-python/`) en PHP + Laravel.
El negocio y el contrato HTTP se respetan; la tecnología se traduce.

## Arranque

```powershell
copy .env.example .env      # luego define JWT_SECRET (32+ caracteres) y ADMIN_PASSWORD
docker compose -f docker-compose.dev.yml up --build
```

Al arrancar: instala dependencias, corre las migraciones (la base empieza vacía), crea el admin desde el entorno y sirve en `http://localhost:8000/api/v1`.

```powershell
curl http://localhost:8000/api/v1/health
docker compose -f docker-compose.dev.yml exec api php artisan test
```

## De Python a Laravel (decisiones de traducción)

| Spec (Python) | Aquí (Laravel) |
|---|---|
| FastAPI | Rutas y controladores de Laravel; `bootstrap/app.php` fija el prefijo `/api/v1` |
| SQLAlchemy + Alembic | Migraciones en SQL explícito: el esquema `library` sale de `data-model.md`, sin `DEFAULT` |
| Dominio puro (art. I) | `app/Domain`: `LoanStatus`, `Isbn`, `LoanRules`, solo PHP sin framework |
| Servicios de aplicación (SRP) | `app/Services`: `LoanService`, `BookService`, `MemberService`, `ReportService`, `AuthService` |
| Error único (RNF-4) | `ApiException` + `ErrorResponder`, único lugar que arma `{code, message, details}` |
| JWT HS256, 8 h | `firebase/php-jwt` tras `JwtService`; middleware `jwt` y `role` |
| Argon2id | `HASH_DRIVER=argon2id` |
| Testigo de versión (ADR-002) | `UPDATE … SET available_copies = available_copies - 1 WHERE available_copies > 0` dentro de la transacción; 0 filas afectadas → `409 BOOK_NOT_AVAILABLE`. La última barrera sigue siendo `ck_book_available_range` |
| Reporte (ADR-004) | Una consulta agregada sobre `loan_item.book_title` y `category_name` congelados |

## Supuestos donde el spec calla

- Token ausente, inválido o vencido: `401` con `code: UNAUTHENTICATED` (el contrato solo nombra `INVALID_CREDENTIALS`).
- Error inesperado: `500` con `code: INTERNAL_ERROR`, sin trazas ni SQL.
- `GET /categories` devuelve un arreglo simple, ordenado por nombre.
- Orden de validación al prestar: duplicados (422) → socio (404) → vencidos (409) → tope de 3 (422) → libros (404) → ejemplares (409).
- `days_late` son días completos de retraso.

## Estado por tarea

T-01 a T-11 y T-14 a T-18 implementadas en esta base; la evidencia está en `tests/` y en los comandos de arriba. T-19 a T-23 viven en los demás repos o quedan pendientes.
