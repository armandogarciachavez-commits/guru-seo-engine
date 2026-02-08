# Documentación de API - Guru SEO Engine
## Guía de Integración para Desarrolladores

Bienvenido a la API pública de Guru SEO Engine. Esta documentación le ayudará a integrar los artículos generados por nuestra IA directamente en su sitio web o aplicación, sin necesidad de plugins complejos.

---

### 1. Endpoint Principal

**Método:** `GET`
**URL Base:** `https://tudominio.com/api/v1`

#### Obtener Artículos Publicados
Este endpoint devuelve una lista de los últimos 10 artículos generados y publicados para su proyecto.

```http
GET /projects/{PROJECT_UUID}/articles
```

**Parámetros:**
- `{PROJECT_UUID}` (Requerido): El Identificador \u00danico Universal de su proyecto. Puede encontrarlo en su Panel de Administración bajo "Project UUID".

**Encabezados Recomendados:**
```http
Accept: application/json
```

---

### 2. Ejemplo de Petición

**cURL:**
```bash
curl -X GET "https://tudominio.com/api/v1/projects/7d313ef3-5ff1-49a7-a8bf-1daa729ad456/articles" \
     -H "Accept: application/json"
```

**JavaScript (Fetch):**
```javascript
const projectUUID = '7d313ef3-5ff1-49a7-a8bf-1daa729ad456';
const url = `https://tudominio.com/api/v1/projects/${projectUUID}/articles`;

fetch(url)
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error('Error:', error));
```

---

### 3. Respuesta JSON

La API devolverá un arreglo de objetos JSON con la siguiente estructura:

```json
[
  {
    "title": "Arneses de Seguridad en Manzanillo",
    "slug": "arneses-de-seguridad-manzanillo-2026",
    "html_content": "<h1>Arneses de Seguridad...</h1><p>Contenido del artículo...</p>",
    "created_at": "2026-02-07T22:17:57.000000Z",
    "thumbnail_url": "https://tudominio.com/storage/images/ejemplo.jpg"
  },
  {
    "title": "Optimización SEO para Empresas",
    "slug": "optimizacion-seo-empresas",
    "html_content": "...",
    "created_at": "2026-02-07T21:55:57.000000Z",
    "thumbnail_url": null
  }
]
```

**Descripción de Campos:**
- `title`: El título optimizado para SEO del artículo.
- `slug`: URL amigable sugerida para el artículo.
- `html_content`: El contenido completo del post en formato HTML, listo para ser insertado. Incluye etiquetas H1, H2, P, listas, etc.
- `created_at`: Fecha de generación en formato ISO 8601.
- `thumbnail_url`: (Opcional) URL de la imagen destacada si está disponible.

---

### 4. Códigos de Error

- **200 OK**: Petición exitosa.
- **404 Not Found**: El `PROJECT_UUID` no existe o es incorrecto.

---

### 5. Notas de Implementación

- **CORS**: Esta API tiene habilitado Cross-Origin Resource Sharing (CORS), por lo que puede ser consumida directamente desde navegadores web (frontend JS).
- **Seguridad**: El `PROJECT_UUID` actúa como una llave pública de lectura. Compártalo solo con desarrolladores autorizados.
- **Formato**: El contenido en `html_content` es seguro, pero se recomienda siempre sanitizar cualquier HTML externo antes de renderizarlo en su aplicación si tiene políticas estrictas de seguridad.
