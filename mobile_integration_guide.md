# Руководство по интеграции API для мобильных приложений

Это руководство поможет вам быстро интегрировать Currency API в ваше мобильное приложение iOS или Android.

## Содержание

1. [Быстрый старт](#быстрый-старт)
2. [iOS интеграция (Swift)](#ios-интеграция-swift)
3. [Android интеграция (Kotlin)](#android-интеграция-kotlin)
4. [Обработка ошибок](#обработка-ошибок)
5. [Кэширование](#кэширование)
6. [Лучшие практики](#лучшие-практики)

---

## Быстрый старт

### Базовый URL API

```
Разработка: http://localhost:8000/api
Продакшн: https://api.example.com/api
```

### Основные endpoints

- `GET /api/rates` - Получить все курсы валют
- `GET /api/rates?base=USD&target=EUR` - Получить конкретный курс
- `GET /api/convert?amount=100&from=USD&to=EUR` - Конвертировать валюту
- `POST /api/device/register` - Зарегистрировать устройство

---

## iOS интеграция (Swift)

### 1. Создание API клиента

```swift
import Foundation

class CurrencyAPIClient {
    static let shared = CurrencyAPIClient()
    
    private let baseURL = "https://api.example.com/api"
    private let session = URLSession.shared
    
    private init() {}
    
    // MARK: - Регистрация устройства
    
    func registerDevice(deviceId: String, deviceName: String, appVersion: String, completion: @escaping (Result<DeviceRegisterResponse, Error>) -> Void) {
        let url = URL(string: "\(baseURL)/device/register")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        
        let body: [String: Any] = [
            "device_id": deviceId,
            "device_name": deviceName,
            "platform": "iOS",
            "device_type": "mobile",
            "app_version": appVersion
        ]
        
        request.httpBody = try? JSONSerialization.data(withJSONObject: body)
        
        session.dataTask(with: request) { data, response, error in
            if let error = error {
                completion(.failure(error))
                return
            }
            
            guard let data = data else {
                completion(.failure(APIError.noData))
                return
            }
            
            do {
                let response = try JSONDecoder().decode(DeviceRegisterResponse.self, from: data)
                completion(.success(response))
            } catch {
                completion(.failure(error))
            }
        }.resume()
    }
    
    // MARK: - Получение курсов валют
    
    func getRates(base: String = "USD", target: String? = nil, completion: @escaping (Result<ExchangeRatesResponse, Error>) -> Void) {
        var urlString = "\(baseURL)/rates?base=\(base)"
        if let target = target {
            urlString += "&target=\(target)"
        }
        
        guard let url = URL(string: urlString) else {
            completion(.failure(APIError.invalidURL))
            return
        }
        
        session.dataTask(with: url) { data, response, error in
            if let error = error {
                completion(.failure(error))
                return
            }
            
            guard let data = data else {
                completion(.failure(APIError.noData))
                return
            }
            
            do {
                let response = try JSONDecoder().decode(ExchangeRatesResponse.self, from: data)
                completion(.success(response))
            } catch {
                completion(.failure(error))
            }
        }.resume()
    }
    
    // MARK: - Конвертация валют
    
    func convert(amount: Double, from: String, to: String, completion: @escaping (Result<ConvertResponse, Error>) -> Void) {
        let urlString = "\(baseURL)/convert?amount=\(amount)&from=\(from)&to=\(to)"
        
        guard let url = URL(string: urlString) else {
            completion(.failure(APIError.invalidURL))
            return
        }
        
        session.dataTask(with: url) { data, response, error in
            if let error = error {
                completion(.failure(error))
                return
            }
            
            guard let data = data else {
                completion(.failure(APIError.noData))
                return
            }
            
            do {
                let response = try JSONDecoder().decode(ConvertResponse.self, from: data)
                completion(.success(response))
            } catch {
                completion(.failure(error))
            }
        }.resume()
    }
}

// MARK: - Модели данных

struct DeviceRegisterResponse: Codable {
    let success: Bool
    let message: String
    let deviceId: Int
    
    enum CodingKeys: String, CodingKey {
        case success, message
        case deviceId = "device_id"
    }
}

struct ExchangeRatesResponse: Codable {
    let success: Bool
    let base: String
    let rates: [String: RateInfo]?
    let target: String?
    let rate: Double?
    let count: Int?
    let lastUpdated: String?
    
    enum CodingKeys: String, CodingKey {
        case success, base, rates, target, rate, count
        case lastUpdated = "last_updated"
    }
}

struct RateInfo: Codable {
    let rate: Double
    let lastUpdated: String
    
    enum CodingKeys: String, CodingKey {
        case rate
        case lastUpdated = "last_updated"
    }
}

struct ConvertResponse: Codable {
    let success: Bool
    let amount: Double
    let from: String
    let to: String
    let convertedAmount: Double
    let rate: Double
    let lastUpdated: String
    
    enum CodingKeys: String, CodingKey {
        case success, amount, from, to, rate
        case convertedAmount = "converted_amount"
        case lastUpdated = "last_updated"
    }
}

enum APIError: Error {
    case noData
    case invalidURL
    case invalidResponse
    case networkError(String)
}
```

### 2. Использование в приложении

```swift
import UIKit

class ViewController: UIViewController {
    
    override func viewDidLoad() {
        super.viewDidLoad()
        
        // Регистрация устройства при первом запуске
        registerDeviceIfNeeded()
        
        // Получение курсов валют
        loadExchangeRates()
    }
    
    private func registerDeviceIfNeeded() {
        // Проверяем, зарегистрировано ли устройство
        if UserDefaults.standard.string(forKey: "device_id") == nil {
            let deviceId = UIDevice.current.identifierForVendor?.uuidString ?? UUID().uuidString
            let deviceName = UIDevice.current.name
            let appVersion = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "1.0.0"
            
            CurrencyAPIClient.shared.registerDevice(
                deviceId: deviceId,
                deviceName: deviceName,
                appVersion: appVersion
            ) { result in
                switch result {
                case .success(let response):
                    if response.success {
                        UserDefaults.standard.set(deviceId, forKey: "device_id")
                        print("Устройство зарегистрировано: \(response.message)")
                    }
                case .failure(let error):
                    print("Ошибка регистрации: \(error.localizedDescription)")
                }
            }
        }
    }
    
    private func loadExchangeRates() {
        CurrencyAPIClient.shared.getRates(base: "USD", target: "EUR") { result in
            switch result {
            case .success(let response):
                if response.success {
                    if let rate = response.rate {
                        print("Курс USD/EUR: \(rate)")
                    } else if let rates = response.rates {
                        print("Получено курсов: \(rates.count)")
                    }
                }
            case .failure(let error):
                print("Ошибка загрузки курсов: \(error.localizedDescription)")
            }
        }
    }
    
    private func convertCurrency(amount: Double, from: String, to: String) {
        CurrencyAPIClient.shared.convert(amount: amount, from: from, to: to) { result in
            switch result {
            case .success(let response):
                if response.success {
                    print("\(response.amount) \(response.from) = \(response.convertedAmount) \(response.to)")
                    print("Курс: \(response.rate)")
                }
            case .failure(let error):
                print("Ошибка конвертации: \(error.localizedDescription)")
            }
        }
    }
}
```

### 3. Кэширование курсов

```swift
class ExchangeRateCache {
    static let shared = ExchangeRateCache()
    private let cacheKey = "exchange_rates_cache"
    private let cacheExpirationKey = "cache_expiration"
    private let cacheDuration: TimeInterval = 3600 // 1 час
    
    private init() {}
    
    func saveRates(_ rates: [String: RateInfo], base: String) {
        let cacheData: [String: Any] = [
            "rates": rates,
            "base": base,
            "timestamp": Date().timeIntervalSince1970
        ]
        
        if let data = try? JSONEncoder().encode(cacheData) {
            UserDefaults.standard.set(data, forKey: cacheKey)
            UserDefaults.standard.set(Date().addingTimeInterval(cacheDuration), forKey: cacheExpirationKey)
        }
    }
    
    func getCachedRates() -> [String: RateInfo]? {
        guard let expirationDate = UserDefaults.standard.object(forKey: cacheExpirationKey) as? Date,
              expirationDate > Date() else {
            return nil
        }
        
        guard let data = UserDefaults.standard.data(forKey: cacheKey),
              let cacheData = try? JSONDecoder().decode([String: Any].self, from: data),
              let ratesDict = cacheData["rates"] as? [String: [String: Any]] else {
            return nil
        }
        
        var rates: [String: RateInfo] = [:]
        for (key, value) in ratesDict {
            if let rate = value["rate"] as? Double,
               let lastUpdated = value["last_updated"] as? String {
                rates[key] = RateInfo(rate: rate, lastUpdated: lastUpdated)
            }
        }
        
        return rates
    }
    
    func isCacheValid() -> Bool {
        guard let expirationDate = UserDefaults.standard.object(forKey: cacheExpirationKey) as? Date else {
            return false
        }
        return expirationDate > Date()
    }
}
```

---

## Android интеграция (Kotlin)

### 1. Создание API клиента

```kotlin
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.IOException

class CurrencyAPIClient(private val baseURL: String = "https://api.example.com/api") {
    
    private val client = OkHttpClient()
    private val jsonMediaType = "application/json".toMediaType()
    
    // MARK: - Регистрация устройства
    
    fun registerDevice(
        deviceId: String,
        deviceName: String,
        appVersion: String,
        callback: (Result<DeviceRegisterResponse>) -> Unit
    ) {
        val url = "$baseURL/device/register"
        
        val requestBody = JSONObject().apply {
            put("device_id", deviceId)
            put("device_name", deviceName)
            put("platform", "Android")
            put("device_type", "mobile")
            put("app_version", appVersion)
        }
        
        val request = Request.Builder()
            .url(url)
            .post(requestBody.toString().toRequestBody(jsonMediaType))
            .addHeader("Content-Type", "application/json")
            .build()
        
        client.newCall(request).enqueue(object : Callback {
            override fun onResponse(call: Call, response: Response) {
                try {
                    val json = JSONObject(response.body?.string() ?: "")
                    val deviceResponse = DeviceRegisterResponse(
                        success = json.getBoolean("success"),
                        message = json.getString("message"),
                        deviceId = json.getInt("device_id")
                    )
                    callback(Result.success(deviceResponse))
                } catch (e: Exception) {
                    callback(Result.failure(e))
                }
            }
            
            override fun onFailure(call: Call, e: IOException) {
                callback(Result.failure(e))
            }
        })
    }
    
    // MARK: - Получение курсов валют
    
    fun getRates(
        base: String = "USD",
        target: String? = null,
        callback: (Result<ExchangeRatesResponse>) -> Unit
    ) {
        var url = "$baseURL/rates?base=$base"
        if (target != null) {
            url += "&target=$target"
        }
        
        val request = Request.Builder()
            .url(url)
            .get()
            .build()
        
        client.newCall(request).enqueue(object : Callback {
            override fun onResponse(call: Call, response: Response) {
                try {
                    val json = JSONObject(response.body?.string() ?: "")
                    val ratesResponse = ExchangeRatesResponse.fromJson(json)
                    callback(Result.success(ratesResponse))
                } catch (e: Exception) {
                    callback(Result.failure(e))
                }
            }
            
            override fun onFailure(call: Call, e: IOException) {
                callback(Result.failure(e))
            }
        })
    }
    
    // MARK: - Конвертация валют
    
    fun convert(
        amount: Double,
        from: String,
        to: String,
        callback: (Result<ConvertResponse>) -> Unit
    ) {
        val url = "$baseURL/convert?amount=$amount&from=$from&to=$to"
        
        val request = Request.Builder()
            .url(url)
            .get()
            .build()
        
        client.newCall(request).enqueue(object : Callback {
            override fun onResponse(call: Call, response: Response) {
                try {
                    val json = JSONObject(response.body?.string() ?: "")
                    val convertResponse = ConvertResponse.fromJson(json)
                    callback(Result.success(convertResponse))
                } catch (e: Exception) {
                    callback(Result.failure(e))
                }
            }
            
            override fun onFailure(call: Call, e: IOException) {
                callback(Result.failure(e))
            }
        })
    }
}

// MARK: - Модели данных

data class DeviceRegisterResponse(
    val success: Boolean,
    val message: String,
    val deviceId: Int
)

data class ExchangeRatesResponse(
    val success: Boolean,
    val base: String,
    val rates: Map<String, RateInfo>?,
    val target: String?,
    val rate: Double?,
    val count: Int?,
    val lastUpdated: String?
) {
    companion object {
        fun fromJson(json: JSONObject): ExchangeRatesResponse {
            val ratesMap = if (json.has("rates")) {
                val ratesJson = json.getJSONObject("rates")
                ratesJson.keys().associateWith { key ->
                    val rateJson = ratesJson.getJSONObject(key)
                    RateInfo(
                        rate = rateJson.getDouble("rate"),
                        lastUpdated = rateJson.getString("last_updated")
                    )
                }
            } else null
            
            return ExchangeRatesResponse(
                success = json.getBoolean("success"),
                base = json.getString("base"),
                rates = ratesMap,
                target = json.optString("target"),
                rate = json.optDouble("rate").takeIf { !it.isNaN() },
                count = json.optInt("count").takeIf { it > 0 },
                lastUpdated = json.optString("last_updated")
            )
        }
    }
}

data class RateInfo(
    val rate: Double,
    val lastUpdated: String
)

data class ConvertResponse(
    val success: Boolean,
    val amount: Double,
    val from: String,
    val to: String,
    val convertedAmount: Double,
    val rate: Double,
    val lastUpdated: String
) {
    companion object {
        fun fromJson(json: JSONObject): ConvertResponse {
            return ConvertResponse(
                success = json.getBoolean("success"),
                amount = json.getDouble("amount"),
                from = json.getString("from"),
                to = json.getString("to"),
                convertedAmount = json.getDouble("converted_amount"),
                rate = json.getDouble("rate"),
                lastUpdated = json.getString("last_updated")
            )
        }
    }
}
```

### 2. Использование в приложении

```kotlin
import android.content.Context
import android.content.SharedPreferences
import android.provider.Settings

class MainActivity : AppCompatActivity() {
    
    private lateinit var apiClient: CurrencyAPIClient
    private lateinit var prefs: SharedPreferences
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        
        apiClient = CurrencyAPIClient()
        prefs = getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
        
        // Регистрация устройства при первом запуске
        registerDeviceIfNeeded()
        
        // Получение курсов валют
        loadExchangeRates()
    }
    
    private fun registerDeviceIfNeeded() {
        val deviceId = prefs.getString("device_id", null)
        
        if (deviceId == null) {
            val androidId = Settings.Secure.getString(
                contentResolver,
                Settings.Secure.ANDROID_ID
            )
            val deviceName = android.os.Build.MODEL
            val appVersion = BuildConfig.VERSION_NAME
            
            apiClient.registerDevice(androidId, deviceName, appVersion) { result ->
                result.onSuccess { response ->
                    if (response.success) {
                        prefs.edit().putString("device_id", androidId).apply()
                        println("Устройство зарегистрировано: ${response.message}")
                    }
                }.onFailure { error ->
                    println("Ошибка регистрации: ${error.message}")
                }
            }
        }
    }
    
    private fun loadExchangeRates() {
        apiClient.getRates(base = "USD", target = "EUR") { result ->
            result.onSuccess { response ->
                if (response.success) {
                    response.rate?.let { rate ->
                        println("Курс USD/EUR: $rate")
                    } ?: response.rates?.let { rates ->
                        println("Получено курсов: ${rates.size}")
                    }
                }
            }.onFailure { error ->
                println("Ошибка загрузки курсов: ${error.message}")
            }
        }
    }
    
    private fun convertCurrency(amount: Double, from: String, to: String) {
        apiClient.convert(amount, from, to) { result ->
            result.onSuccess { response ->
                if (response.success) {
                    println("${response.amount} ${response.from} = ${response.convertedAmount} ${response.to}")
                    println("Курс: ${response.rate}")
                }
            }.onFailure { error ->
                println("Ошибка конвертации: ${error.message}")
            }
        }
    }
}
```

### 3. Кэширование курсов

```kotlin
import android.content.Context
import android.content.SharedPreferences
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken

class ExchangeRateCache(context: Context) {
    
    private val prefs: SharedPreferences = context.getSharedPreferences("rates_cache", Context.MODE_PRIVATE)
    private val gson = Gson()
    private val cacheDuration = 3600_000L // 1 час в миллисекундах
    
    fun saveRates(rates: Map<String, RateInfo>, base: String) {
        val cacheData = RatesCacheData(
            rates = rates,
            base = base,
            timestamp = System.currentTimeMillis()
        )
        
        val json = gson.toJson(cacheData)
        prefs.edit()
            .putString("rates_data", json)
            .putLong("cache_expiration", System.currentTimeMillis() + cacheDuration)
            .apply()
    }
    
    fun getCachedRates(): Map<String, RateInfo>? {
        val expirationTime = prefs.getLong("cache_expiration", 0)
        if (System.currentTimeMillis() > expirationTime) {
            return null
        }
        
        val json = prefs.getString("rates_data", null) ?: return null
        val type = object : TypeToken<RatesCacheData>() {}.type
        val cacheData: RatesCacheData = gson.fromJson(json, type)
        
        return cacheData.rates
    }
    
    fun isCacheValid(): Boolean {
        val expirationTime = prefs.getLong("cache_expiration", 0)
        return System.currentTimeMillis() < expirationTime
    }
    
    private data class RatesCacheData(
        val rates: Map<String, RateInfo>,
        val base: String,
        val timestamp: Long
    )
}
```

---

## Обработка ошибок

### Общие рекомендации

1. **Всегда проверяйте поле `success` в ответе**
2. **Обрабатывайте сетевые ошибки gracefully**
3. **Используйте кэшированные данные при ошибках сети**
4. **Показывайте понятные сообщения пользователю**

### Пример обработки ошибок (Swift)

```swift
func handleAPIError(_ error: Error) {
    if let apiError = error as? APIError {
        switch apiError {
        case .noData:
            showError("Нет данных от сервера")
        case .invalidURL:
            showError("Неверный URL")
        case .networkError(let message):
            showError("Ошибка сети: \(message)")
        default:
            showError("Неизвестная ошибка")
        }
    } else {
        showError("Ошибка: \(error.localizedDescription)")
    }
}
```

### Пример обработки ошибок (Kotlin)

```kotlin
fun handleAPIError(error: Throwable) {
    when (error) {
        is IOException -> showError("Ошибка сети. Проверьте подключение к интернету.")
        is JSONException -> showError("Ошибка обработки данных")
        else -> showError("Ошибка: ${error.message}")
    }
}
```

---

## Кэширование

### Рекомендации

1. **Кэшируйте курсы на 1 час** (время обновления на сервере)
2. **Обновляйте кэш в фоновом режиме**
3. **Используйте кэш при отсутствии сети**
4. **Показывайте индикатор устаревших данных**

### Стратегия кэширования

```swift
// Swift пример
func getRatesWithCache(base: String, target: String?, completion: @escaping (Result<ExchangeRatesResponse, Error>) -> Void) {
    // Проверяем кэш
    if let cachedRates = ExchangeRateCache.shared.getCachedRates(),
       ExchangeRateCache.shared.isCacheValid() {
        // Используем кэш
        let response = ExchangeRatesResponse(
            success: true,
            base: base,
            rates: cachedRates,
            target: nil,
            rate: nil,
            count: cachedRates.count,
            lastUpdated: nil
        )
        completion(.success(response))
    }
    
    // Загружаем свежие данные
    CurrencyAPIClient.shared.getRates(base: base, target: target) { result in
        switch result {
        case .success(let response):
            if let rates = response.rates {
                ExchangeRateCache.shared.saveRates(rates, base: base)
            }
            completion(.success(response))
        case .failure(let error):
            // При ошибке используем кэш, если есть
            if let cachedRates = ExchangeRateCache.shared.getCachedRates() {
                // Возвращаем кэшированные данные
            } else {
                completion(.failure(error))
            }
        }
    }
}
```

---

## Лучшие практики

### 1. Регистрация устройства

- ✅ Регистрируйте при первом запуске
- ✅ Обновляйте информацию при обновлении приложения
- ✅ Сохраняйте device_id локально
- ✅ Используйте UUID (iOS) или Android ID (Android)

### 2. Запросы к API

- ✅ Кэшируйте курсы локально
- ✅ Используйте параметр `target` для конкретных курсов
- ✅ Валидируйте данные на клиенте
- ✅ Обрабатывайте все ошибки

### 3. UX

- ✅ Показывайте индикатор загрузки
- ✅ Используйте кэш для мгновенного отображения
- ✅ Обновляйте данные в фоне
- ✅ Показывайте время последнего обновления
- ✅ Работайте офлайн с кэшированными данными

### 4. Производительность

- ✅ Минимизируйте количество запросов
- ✅ Группируйте запросы где возможно
- ✅ Используйте фоновые задачи для обновления
- ✅ Оптимизируйте размер ответов

---

## Дополнительные ресурсы

- [YAML документация API](mobile_api_documentation.yaml)
- [Полная документация API](API_DOCUMENTATION_COMPLETE.md)
- [Примеры использования](api_examples.md)

---

## Поддержка

Если у вас возникли вопросы или проблемы с интеграцией, обратитесь к документации или создайте issue в репозитории проекта.

