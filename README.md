# Symfony Parser

This project is a Symfony-based application designed to parse and process various types of data. It provides a robust and flexible framework for handling different parsing tasks.

## Features

- Easy to configure and extend
- Supports multiple data formats (e.g., JSON, XML, CSV)
- Built-in error handling and logging
- High performance and scalability

## Requirements

- PHP 7.4 or higher
- Composer
- Symfony 5.0 or higher

## Installation

1. Clone the repository:
    ```
    git clone https://github.com/tulbadex/symfony-docker-parser-apache.git
    ```

2. Navigate to the project directory:
    ```
    cd symfony-parser
    ```

3. Install the dependencies:
    ```
    composer install
    ```

## Usage

1. Run the app:
    ```
    <!-- build the docker file -->
    docker-compose build --no-cache

    <!-- build the app -->
    docker-compose up -d  

    <!-- check if app is completed -->
    docker-compose logs -f app

    <!-- create a user account -->
    docker-compose exec app php bin/console app:create-user test@example.com password123 user

    <!-- create admin account -->
    docker-compose exec app php bin/console app:create-user test@example.com password123 admin
    ```

## Contributing

Contributions are welcome! Please submit a pull request or open an issue to discuss any changes.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Contact

For any questions or inquiries, please contact [ibrahimadedayo@rocketmail.com](mailto:ibrahimadedayo@rocketmail.com).