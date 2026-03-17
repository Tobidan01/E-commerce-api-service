# 🛒 E-Commerce API Service

A production-ready RESTful E-Commerce backend API built **from scratch in PHP — no framework**. Features JWT authentication, Google OAuth, Paystack payment integration, and full order management. Deployed with Docker on Render.

> Built by Software Engineering Intern in one month, starting from Node.js and picking up PHP along the way.

---

## 🔗 Links

|              |                                                     |
| ------------ | --------------------------------------------------- |
| **Live API** | https://e-commerce-api-5khm.onrender.com            |
| **GitHub**   | https://github.com/Tobidan01/E-commerce-api-service |

---

## ✨ Features

### 🔐 Authentication

- JWT-based registration & login
- Google OAuth (Sign in with Google)
- Role-based access control (`user` & `admin`)
- Token expiry & signature verification

### 🛍️ Products

- Product management with variants (size, color, stock)
- Product images
- Flash sales with time-based expiry
- Category system with parent/child relationships
- Product reviews & ratings

### 🛒 Shopping

- Cart (add, update quantity, remove, clear)
- Wishlist
- Order placement & full management
- Order status timeline (`pending → processing → shipped → delivered`)
- Admin dashboard with stats

### 💳 Payments

- **Paystack** integration (card, bank transfer, USSD)
- Cash on delivery
- Webhook handling with HMAC-SHA512 signature verification
- Duplicate payment prevention
- Payment verification endpoint

### ⚙️ Infrastructure

- Custom MVC Router with PHP Reflection & dependency injection
- Singleton database connection pattern
- Dockerized deployment
- Hosted on **Render**
- MySQL database in production

---

## 🏗️ Architecture

```
E-COMMERCE/
├── app/
│   ├── Config/          ← Database connection (Singleton PDO)
│   ├── Controllers/     ← Handle HTTP requests
│   ├── Core/            ← Custom Router with dependency injection
│   ├── Helpers/         ← JWT, Response, Validator
│   ├── Models/          ← Raw SQL queries
│   └── Services/        ← Business logic layer
├── routes/              ← API endpoint definitions
├── config.php           ← Environment configuration
├── index.php            ← Application entry point
├── Dockerfile           ← Docker configuration
└── render.yaml          ← Render deployment config
```

### Request Flow

```
Request → Router → Controller → Service → Model → Database
                                                      ↓
Response ← Controller ← Service ← Model ←────────────┘
```

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.3+
- MySQL 5.7+
- Composer
- Docker (optional)

### Local Setup

**1. Clone the repository**

```bash
git clone https://github.com/Tobidan01/E-commerce-api-service.git
cd E-commerce-api-service
```

**2. Install dependencies**

```bash
composer install
```

**3. Create `.env` file**

```env
APP_ENV=development

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=ecommerce
DB_USER=your_db_user
DB_PASS=your_db_password

JWT_SECRET=your_super_long_random_secret
JWT_EXPIRES_IN=3600

GOOGLE_CLIENT_ID=your_google_client_id

PAYSTACK_SECRET_KEY=sk_test_xxxxx
PAYSTACK_PUBLIC_KEY=pk_test_xxxxx

APP_URL=http://localhost/your-project-path
```

**4. Import the database**

Run the SQL schema file in your MySQL client:

```bash
mysql -u root -p ecommerce < database/schema.sql
```

**5. Configure Apache / Laragon**

Make sure `.htaccess` is enabled and `mod_rewrite` is active.

**6. Test the API**

```bash
curl http://localhost/your-project/api/auth/register \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"first_name":"John","last_name":"Doe","email":"john@example.com","password":"password123"}'
```

---

### Docker Setup

```bash
# Build image
docker build -t ecommerce-api .

# Run container
docker run -p 8080:80 \
  -e DB_HOST=your_db_host \
  -e DB_NAME=your_db_name \
  -e DB_USER=your_db_user \
  -e DB_PASS=your_db_pass \
  -e JWT_SECRET=your_secret \
  ecommerce-api
```

---

## 📡 API Endpoints

### Authentication

| Method | Endpoint             | Description              | Auth |
| ------ | -------------------- | ------------------------ | ---- |
| POST   | `/api/auth/register` | Register new user        | ❌   |
| POST   | `/api/auth/login`    | Login & get JWT token    | ❌   |
| POST   | `/api/auth/google`   | Google OAuth login       | ❌   |
| GET    | `/api/auth/me`       | Get current user profile | ✅   |

### Categories

| Method | Endpoint               | Description         | Auth     |
| ------ | ---------------------- | ------------------- | -------- |
| GET    | `/api/categories`      | Get all categories  | ❌       |
| POST   | `/api/categories`      | Create category     | 👑 Admin |
| GET    | `/api/categories/{id}` | Get single category | ❌       |
| PUT    | `/api/categories/{id}` | Update category     | 👑 Admin |
| DELETE | `/api/categories/{id}` | Delete category     | 👑 Admin |

### Products

| Method | Endpoint                    | Description             | Auth     |
| ------ | --------------------------- | ----------------------- | -------- |
| GET    | `/api/products`             | Get all products        | ❌       |
| GET    | `/api/products/list`        | Get product list        | ❌       |
| GET    | `/api/products/flash-sales` | Get flash sale products | ❌       |
| GET    | `/api/products/{id}`        | Get single product      | ❌       |
| POST   | `/api/products`             | Create product          | 👑 Admin |
| PUT    | `/api/products/{id}`        | Update product          | 👑 Admin |
| DELETE | `/api/products/{id}`        | Delete product          | 👑 Admin |

### Cart

| Method | Endpoint         | Description               | Auth |
| ------ | ---------------- | ------------------------- | ---- |
| GET    | `/api/cart`      | Get user cart             | ✅   |
| POST   | `/api/cart/add`  | Add item to cart          | ✅   |
| PUT    | `/api/cart/{id}` | Update cart item quantity | ✅   |
| DELETE | `/api/cart/{id}` | Remove cart item          | ✅   |
| DELETE | `/api/cart`      | Clear entire cart         | ✅   |

### Wishlist

| Method | Endpoint                    | Description          | Auth |
| ------ | --------------------------- | -------------------- | ---- |
| GET    | `/api/wishlist`             | Get user wishlist    | ✅   |
| POST   | `/api/wishlist/{productId}` | Add to wishlist      | ✅   |
| DELETE | `/api/wishlist/{productId}` | Remove from wishlist | ✅   |

### Orders

| Method | Endpoint           | Description      | Auth |
| ------ | ------------------ | ---------------- | ---- |
| GET    | `/api/orders`      | Get my orders    | ✅   |
| GET    | `/api/orders/{id}` | Get single order | ✅   |
| DELETE | `/api/orders/{id}` | Cancel order     | ✅   |

### Admin — Orders

| Method | Endpoint                          | Description         | Auth     |
| ------ | --------------------------------- | ------------------- | -------- |
| GET    | `/api/admin/orders`               | Get all orders      | 👑 Admin |
| PUT    | `/api/admin/orders/{id}/status`   | Update order status | 👑 Admin |
| GET    | `/api/admin/orders/{id}/timeline` | Get order timeline  | 👑 Admin |
| GET    | `/api/admin/dashboard`            | Get dashboard stats | 👑 Admin |

### Checkout & Payments

| Method | Endpoint                           | Description                  | Auth |
| ------ | ---------------------------------- | ---------------------------- | ---- |
| POST   | `/api/checkout/cash`               | Place cash on delivery order | ✅   |
| POST   | `/api/checkout/initiate`           | Initiate Paystack payment    | ✅   |
| GET    | `/api/checkout/verify/{reference}` | Verify payment               | ❌   |
| POST   | `/api/checkout/webhook`            | Paystack webhook handler     | ❌   |

**Legend:** ✅ User token required &nbsp;&nbsp; 👑 Admin token required &nbsp;&nbsp; ❌ Public

---

## 📦 Request & Response Examples

### Register

```http
POST /api/auth/register
Content-Type: application/json

{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "password": "password123",
  "phone": "08012345678"
}
```

```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "role": "user",
    "token": "eyJhbGciOiJIUzI1NiJ9..."
  }
}
```

### Initiate Payment

```http
POST /api/checkout/initiate
Authorization: Bearer eyJhbGciOiJIUzI1NiJ9...
Content-Type: application/json

{
  "email": "john@example.com",
  "address": "12 Lagos Street",
  "city": "Lagos",
  "state": "Lagos",
  "country": "Nigeria"
}
```

```json
{
  "success": true,
  "message": "Payment initiated",
  "data": {
    "payment_url": "https://checkout.paystack.com/xxxxx",
    "reference": "ECM-ABC123XYZ",
    "amount": 6380
  }
}
```

---

## 💳 Payment Flow

```
1. POST /api/checkout/initiate
        ↓
   Returns payment_url
        ↓
2. User pays on Paystack (card/bank/USSD)
        ↓
   ┌────────────────────────────────┐
   │                                │
3. Webhook fires (server→server)  4. Browser redirects to verify
   → Creates order ✅               → Shows success page ✅
   → Most reliable                  → User facing
   └────────────────────────────────┘
```

### Test Card (Paystack Test Mode)

```
Card Number: 4084 0840 8408 4081
Expiry:      01/25
CVV:         408
PIN:         0000
OTP:         123456
```

---

## 🔒 Security

- **JWT Authentication** — stateless token-based auth with expiry
- **Webhook Signature Verification** — HMAC-SHA512 to validate Paystack requests
- **Prepared Statements** — all SQL queries use PDO prepared statements (prevents SQL injection)
- **Password Hashing** — bcrypt via PHP's `password_hash()`
- **Role-based Access** — admin routes protected separately from user routes
- **Environment Variables** — all secrets stored in `.env`, never committed to git

---

## 🛠️ Tech Stack

| Layer          | Technology                |
| -------------- | ------------------------- |
| Language       | PHP 8.3                   |
| Database       | MySQL                     |
| Authentication | JWT (firebase/php-jwt)    |
| OAuth          | Google OAuth 2.0          |
| Payments       | Paystack                  |
| Deployment     | Docker + Render           |
| Environment    | vlucas/phpdotenv          |
| Architecture   | Custom MVC (no framework) |

---

## 🧠 Key Concepts Implemented

**Custom Dependency Injection**

```php
// Router uses PHP Reflection to auto-create controllers:
// Reads: AuthController(__construct(AuthService $s, array $config))
// Creates: new AuthController(new AuthService($config), $config)
```

**Singleton Database Pattern**

```php
// Only ONE database connection created for entire request lifecycle
private static ?PDO $connection = null;
```

**Webhook Security**

```php
$expected = hash_hmac('sha512', $payload, $paystackSecret);
if ($signature !== $expected) { /* reject */ }
```

---

## 🌍 Deployment

This API is deployed using Docker on Render.

### Environment Variables (Render)

```
APP_ENV=production
DB_HOST=your_db_host
DB_PORT=3306
DB_NAME=your_db_name
DB_USER=your_db_user
DB_PASS=your_db_password
JWT_SECRET=your_jwt_secret
JWT_EXPIRES_IN=3600
GOOGLE_CLIENT_ID=your_google_client_id
PAYSTACK_SECRET_KEY=sk_live_xxxxx
PAYSTACK_PUBLIC_KEY=pk_live_xxxxx
APP_URL=https://your-app.onrender.com
```

---

## 📋 What's Next

- [ ] Image upload with Cloudinary
- [ ] Email notifications with Mailgun
- [ ] React frontend
- [ ] Unit tests with PHPUnit
- [ ] API rate limiting
- [ ] Redis caching

---

## 👤 Author

**Tobi Daniel**
Student & Software Engineering Intern

- GitHub: [@Tobidan01](https://github.com/Tobidan01)
- Live API: [e-commerce-api-5khm.onrender.com](https://e-commerce-api-5khm.onrender.com)

---

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

---

> _Built from scratch to understand what frameworks do under the hood — before letting them do it for me._
