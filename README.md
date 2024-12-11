# BigCommerce Middleware

This project is built on the [Lumen Framework](https://lumen.laravel.com/) and serves as a middleware layer to facilitate communication and data synchronization between a BigCommerce storefront and the Carecloud CRM system.

## Overview

The middleware acts as a bridge, streamlining data flow between BigCommerce and Carecloud. By centralizing integration logic in a dedicated service, it becomes easier to maintain, update, and extend the data connections without disrupting either platform.

## Key Features

- **Lumen Framework:**  
  Lightweight and fast micro-framework based on Laravel, ideal for APIs and middleware services.
  
- **BigCommerce Integration:**  
  Fetch and handle BigCommerce data (such as orders, products, and customers) through its APIs.
  
- **Carecloud CRM Integration:**  
  Synchronize and send data to Carecloud, ensuring that your CRM stays updated with the latest e-commerce data.
  
- **Scalable and Maintainable:**  
  Encapsulating integration logic in a dedicated middleware layer reduces complexity and makes ongoing maintenance simpler.

## Requirements

- **PHP 8.1 or newer**
- **Composer:** Required for installing dependencies.
- **BigCommerce API Credentials:**  
  Obtain API credentials (Client ID, Client Secret, Access Token) from your BigCommerce control panel.
- **Carecloud CRM Credentials:**  
  Access credentials for Carecloud's API must be configured.
  
## Getting Started

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/kukivac/bigcommerce-middleware.git
   cd bigcommerce-middleware
   ```

2. **Install Dependencies:**
   Use Composer to install required PHP dependencies:
   ```bash
   composer install
   ```

3. **Configuration:**
   - Copy the `.env.example` file to `.env`:
     ```bash
     cp .env.example .env
     ```
     
   - Update the `.env` file with your BigCommerce and Carecloud credentials, as well as any database or configuration details you require. For example:
     ```env
     BIGCOMMERCE_CLIENT_ID=your_client_id
     BIGCOMMERCE_CLIENT_SECRET=your_client_secret
     BIGCOMMERCE_ACCESS_TOKEN=your_access_token
     BIGCOMMERCE_STORE_HASH=your_store_hash

     CARECLOUD_API_KEY=your_carecloud_api_key
     CARECLOUD_API_URL=https://your_carecloud_domain/api
     ```

4. **Run the Application:**
   Start the built-in PHP server:
   ```bash
   php -S localhost:8000 -t public
   ```
   
   Visit:
   ```
   http://localhost:8000
   ```
   If authentication or specific endpoints are needed, they will be determined by your routes and controllers.

## Usage

- **Endpoints:**  
  Define routes in `routes/web.php` or `routes/` directory as needed. These might include endpoints to:
  - Fetch and process BigCommerce orders.
  - Push product or customer data to Carecloud.
  - Retrieve synchronization statuses or logs.

- **Extensibility:**  
  Modify or add to the middleware logic to handle additional data types, custom fields, or process transformations as required.

## Troubleshooting

- **Check Logs:**  
  If something isn’t working, review the log output in `storage/logs`. Ensure that credentials and configurations in the `.env` file are correct.
  
- **API Rate Limits & Credentials:**  
  Ensure that your BigCommerce and Carecloud credentials are valid and that you are respecting rate limits. If calls fail, confirm that endpoint URLs and keys are up-to-date.

## Contributing

Contributions are welcome! Feel free to open an issue or submit a pull request to improve functionality, add new features, or enhance documentation. Please follow standard Git workflows and ensure that all changes are thoroughly tested.

## License

This project is licensed under the [MIT License](LICENSE).

---

*This middleware layer provides a stable, maintainable foundation for integrating BigCommerce and Carecloud, allowing you to streamline data flow and enhance your e-commerce operations.*  
