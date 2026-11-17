# CLASS DIAGRAM SISTEM LAUNDRYKU

Diagram kelas ini menyajikan struktur kelas objek utama, relasi pewarisan (*inheritance*), realisasi antarmuka (*interface implementation*), dan dependensi pada arsitektur Pure PHP MVC LaundryKu.

```mermaid
classDiagram
    %% Core & Helpers
    class Database {
        -PDO pdo
        -static Database instance
        +getInstance() Database
        +getConnection() PDO
    }

    class Router {
        -array routes
        +get(string path, mixed handler, array middleware)
        +post(string path, mixed handler, array middleware)
        +dispatch(string uri, string method)
    }

    class Controller {
        #view(string viewName, array data)
        #json(array data, int statusCode)
        #redirect(string url)
    }

    class Model {
        #PDO db
        #string table
        +find(int id) array
        +all() array
        +create(array data) int
        +update(int id, array data) bool
        +delete(int id) bool
    }

    class AuthMiddleware {
        -array allowedRoles
        +handle() bool
    }

    %% Entity Models
    class User {
        +int id
        +string email
        +string password
        +int role_id
        +string phone
        +findByEmail(string email) array
    }

    class Order {
        +int id
        +string order_code
        +int user_id
        +int store_id
        +int courier_id
        +string status
        +string payment_status
        +float total_amount
        +updateStatus(int orderId, string status) bool
    }

    class Payment {
        +int id
        +int order_id
        +float amount
        +string status
        +string payment_method
        +markAsPaid(int paymentId) bool
    }

    class Store {
        +int id
        +string name
        +string address
        +int owner_id
        +bool is_active
    }

    %% Services
    class AiAssistantService {
        -LlmClientInterface client
        -AiSettings aiSettings
        -AiDataProvider aiDataProvider
        +sendMessage(int userId, string message) array
        +buildSystemPrompt(int userId) string
        +isRateLimited(int userId) bool
    }

    class LlmClientInterface {
        <<interface>>
        +generateResponse(string prompt, array history, string message) array
        +testConnection() array
    }

    class GeminiAiClient {
        -string apiKey
        -string model
        +generateResponse(string prompt, array history, string message) array
    }

    class OpenAiCompatibleClient {
        -string apiKey
        -string model
        -string baseUrl
        +generateResponse(string prompt, array history, string message) array
    }

    class MockAiClient {
        +generateResponse(string prompt, array history, string message) array
    }

    class PaymentService {
        -PaymentGatewayInterface gateway
        +createPayment(int orderId, float amount) array
        +processWebhookPayment(array payload) bool
    }

    class WhatsAppNotificationService {
        -WhatsAppClientInterface client
        +sendOrderNotification(int orderId, string eventType) bool
        +sendOmzetReport(int storeId, string phone) bool
    }

    %% Controllers
    class OrderController {
        +index()
        +create()
        +store()
        +detail()
        +updateStatus()
        +weigh()
    }

    class AiChatController {
        -AiAssistantService service
        +chat()
        +getHistory()
        +clear()
    }

    class PaymentController {
        -PaymentService service
        +showQris()
        +checkStatus()
    }

    %% Relationships
    Controller <|-- OrderController
    Controller <|-- AiChatController
    Controller <|-- PaymentController
    Model <|-- User
    Model <|-- Order
    Model <|-- Payment
    Model <|-- Store

    LlmClientInterface <|.. GeminiAiClient
    LlmClientInterface <|.. OpenAiCompatibleClient
    LlmClientInterface <|.. MockAiClient

    AiAssistantService --> LlmClientInterface
    AiChatController --> AiAssistantService
    PaymentController --> PaymentService
    OrderController --> Order
    OrderController --> WhatsAppNotificationService
```
