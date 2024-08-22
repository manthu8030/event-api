# Events API Plugin

**Version:** 1.0  
**Author:** Manthan Desai

## Description

The Events API Plugin is a WordPress plugin that provides a RESTful API for managing events. It allows users to create, update, list, show, and delete events via API requests, with access restricted to administrators.

## Features

- **Custom Post Type:** Registers a custom post type called "Events".
- **Custom Taxonomy:** Registers a custom taxonomy called "Categories" for event categorization.
- **CRUD Operations:** Supports Create, Read, Update, and Delete operations on events via REST API.
- **Token-Based Authentication:** Provides token-based authentication for securing API endpoints.
- **Date Validation:** Validates date formats for start and end date-time fields.

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## Installation

1. Download or clone this repository into your WordPress `wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

### 1. Token Generation

To interact with the API, you'll need to generate a token.

**Endpoint:**  
`POST /wp-json/auth/v1/token`

**Parameters:**  
- `username` (string): Your WordPress username.
- `password` (string): Your WordPress password.

**Response:**  
- `token` (string): The generated token.
- `token_type` (string): The type of token, e.g., `Bearer`.
- `expires_in` (int): The token's expiration time in seconds.

### 2. Using the Token in Requests

For all API requests, include the generated token in the `Authorization` header.

**Example Header:**
Authorization: Bearer YOUR_TOKEN_HERE

### 3. API Endpoints

#### Create Event

**Endpoint:**  
`POST /wp-json/events/v1/create`

**Headers:**
- `Authorization: Bearer YOUR_TOKEN_HERE`

**Parameters:**
- `title` (string): The event title.
- `description` (string): The event description.
- `start_datetime` (string): The event start date and time (format: `YYYY-MM-DD HH:MM:SS`).
- `end_datetime` (string): The event end date and time (format: `YYYY-MM-DD HH:MM:SS`).
- `category` (string): Comma-separated list of event categories.

**Response:**  
- `id` (int): The ID of the created event.
- `message` (string): Success message.

#### Update Event

**Endpoint:**  
`PATCH /wp-json/events/v1/update/{id}`

**Headers:**
- `Authorization: Bearer YOUR_TOKEN_HERE`

**Parameters:**  
- `id` (int): The ID of the event to update.
- `title` (string): The updated event title (optional).
- `description` (string): The updated event description (optional).
- `start_datetime` (string): The updated start date and time (optional).
- `end_datetime` (string): The updated end date and time (optional).
- `category` (string): The updated categories (optional).

**Response:**  
- `id` (int): The ID of the updated event.
- `message` (string): Success message.

#### List Events

**Endpoint:**  
`GET /wp-json/events/v1/list`

**Headers:**
- `Authorization: Bearer YOUR_TOKEN_HERE`

**Parameters:**  
- `date` (string): Filter events by start date (optional, format: `YYYY-MM-DD`).

**Response:**  
- List of events with their details.

#### Show Event

**Endpoint:**  
`GET /wp-json/events/v1/show?id={id}`

**Headers:**
- `Authorization: Bearer YOUR_TOKEN_HERE`

**Parameters:**  
- `id` (int): The ID of the event to show.

**Response:**  
- Event details.

#### Delete Event

**Endpoint:**  
`DELETE /wp-json/events/v1/delete?id={id}`

**Headers:**
- `Authorization: Bearer YOUR_TOKEN_HERE`

**Parameters:**  
- `id` (int): The ID of the event to delete.

**Response:**  
- `message` (string): Success message.

### 4. Permissions

Only administrators are allowed to generate tokens and interact with the Events API. The plugin checks the user’s role before allowing any API operation.

## Customization

- Modify the `$post_slug`, `$post_name`, and `$post_singular_name` properties in the plugin class to change the event post type name.
- Add or remove roles in the `$allowed_roles` array to control which users can interact with the API.


## Future Enhancements

- Advanced Filtering: support filtering by multiple parameters like category, date range, etc.
- Pagination: Implement pagination for the list endpoint
- Webhooks: webhooks to notify other systems when events are created, updated, or deleted.
