<?php
/**
 * Plugin Name: Events API
 * Description: A simple CRUD API for managing events.
 * Version: 1.0
 * Author: Manthan
 */

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

class Events_API_Plugin
{
    private $secret_key = "EVENT_KEYS";
    
	// Post type name
	private $post_slug = "event";
	private $post_name = "Events";
	private $post_singular_name = "Event";
	
	// Add your user role if you want to allow.
    private $allowed_roles = array('administrator');

    public function __construct()
    {
        add_action("init", array($this, "register_event_post_type"));
        add_action("rest_api_init", array($this, "register_routes"));
    }

    // Register custom post type and taxonomy
    public function register_event_post_type()
    {
        register_post_type($this->post_slug, array(
            "labels" => array(
                "name" => __($this->post_name),
                "singular_name" => __($this->post_singular_name),
            ),
            "public" => false,
            "has_archive" => false,
            "show_ui" => true,
            "supports" => array("title", "editor"),
            "capability_type" => "post",
            "capabilities" => array(
                "create_posts" => "do_not_allow", // Disable new posts from UI
            ),
            "map_meta_cap" => true,
        ));

        register_taxonomy($this->post_slug."_category", $this->post_slug, array(
            "label" => "Categories",
            "hierarchical" => true,
            "show_in_rest" => true,
        ));
    }

    // Register REST API routes
    public function register_routes()
    {
        register_rest_route("auth/v1", "/token", [
            "methods" => "POST",
            "callback" => array($this, "custom_auth_token"),
        ]);

        register_rest_route("events/v1", "/create", [
            "methods" => "POST",
            "callback" => array($this, "create_event"),
            "permission_callback" => array($this, "permissions_check"),
        ]);

        register_rest_route("events/v1", "/update/(?P<id>\d+)", [
            "methods" => "PATCH",
            "callback" => array($this, "update_event"),
            "permission_callback" => array($this, "permissions_check"),
        ]);

        register_rest_route("events/v1", "/list", [
            "methods" => "GET",
            "callback" => array($this, "list_events"),
            "permission_callback" => array($this, "permissions_check"),
        ]);

        register_rest_route("events/v1", "/delete", [
            "methods" => "DELETE",
            "callback" => array($this, "delete_event"),
            "permission_callback" => array($this, "permissions_check"),
        ]);

        register_rest_route("events/v1", "/show", [
            "methods" => "GET",
            "callback" => array($this, "show_event"),
            "permission_callback" => array($this, "permissions_check"),
        ]);
    }

    // Permissions check - only allow admins
    public function permissions_check($request){
        $token = $request->get_header("Authorization");

        if (empty($token)) {
            return new WP_Error("missing_token", "Token is required", array(
                "status" => 403,
            ));
        }

        $user_id = $this->validate_token(str_replace("Bearer ", "", $token));
        if (!$user_id) {
            return new WP_Error("invalid_token", "Invalid or expired token", array(
                "status" => 403,
            ));
        }

        return true;
    }

    // Create Event Callback
    public function create_event(WP_REST_Request $request){
        $title = sanitize_text_field($request->get_param("title"));
        $description = sanitize_textarea_field(
            $request->get_param("description")
        );
        $start_datetime = sanitize_text_field(
            $request->get_param("start_datetime")
        );
        $end_datetime = sanitize_text_field(
            $request->get_param("end_datetime")
        );
        $categories = sanitize_text_field($request->get_param("category"));

        if (empty($title) || empty($start_datetime) || empty($end_datetime)) {
            return new WP_Error("missing_data", "Missing required fields", array(
                "status" => 400,
            ));
        }

        if (!$this->validate_date_format($start_datetime) || !$this->validate_date_format($end_datetime) ) {
            return new WP_Error(
                "invalid_date_format",
                "Invalid date format. Please use 'YYYY-MM-DD HH:MM:SS'",
                array("status" => 400)
            );
        }

        if (strtotime($start_datetime) >= strtotime($end_datetime)) {
            return new WP_Error(
                "invalid_date",
                "Start date-time must be before end date-time",
                array("status" => 400)
            );
        }

        $post_id = wp_insert_post(array(
            "post_title" => $title,
            "post_content" => $description,
            "post_type" => $this->post_slug,
            "post_status" => "publish",
            "meta_input" => array(
                "start_datetime" => $start_datetime,
                "end_datetime" => $end_datetime,
            ),
        ));

        if (is_wp_error($post_id)) {
            return new WP_Error(
                "post_creation_failed",
                "Failed to create event",
                array("status" => 500)
            );
        }

        $term_ids = $this->assign_category($categories);

        wp_set_post_terms($post_id, $term_ids, $this->post_slug."_category", false);

        return rest_ensure_response(array(
            "id" => $post_id,
            "message" => "Event created successfully",
        ));
    }

    // Update Event Callback
    public function update_event(WP_REST_Request $request){
        $id = intval($request->get_param("id"));
        $title = sanitize_text_field($request->get_param("title"));
        $description = sanitize_textarea_field( $request->get_param("description") );
        $start_datetime = sanitize_text_field( $request->get_param("start_datetime"));
        $end_datetime = sanitize_text_field( $request->get_param("end_datetime") );
        $categories = sanitize_text_field($request->get_param("category"));

        $event_post = get_post($id);

        if (!$event_post || $event_post->post_type !== $this->post_slug) {
            return new WP_Error("not_found", "Event not found", array(
                "status" => 404,
            ));
        }

        if ( !$this->validate_date_format($start_datetime) || !$this->validate_date_format($end_datetime) ) {
            return new WP_Error(
                "invalid_date_format",
                "Invalid date format. Please use 'YYYY-MM-DD HH:MM:SS'",
                array("status" => 400)
            );
        }

        if (strtotime($start_datetime) >= strtotime($end_datetime)) {
            return new WP_Error(
                "invalid_date",
                "Start date-time must be before end date-time",
                array("status" => 400)
            );
        }

        $post_id = wp_update_post(array(
            "ID" => $id,
            "post_title" => $title,
            "post_content" => $description,
            "meta_input" => array(
                "start_datetime" => $start_datetime,
                "end_datetime" => $end_datetime,
            ),
        ));

        if (is_wp_error($post_id)) {
            return new WP_Error(
                "post_update_failed",
                "Failed to update event",
                array("status" => 500)
            );
        }

        $term_ids = $this->assign_category($categories);

        wp_set_post_terms($post_id, $term_ids, $this->post_slug."_category", false);

        return rest_ensure_response(array(
            "id" => $id,
            "message" => "Event updated successfully",
        ));
    }

    // List Events Callback
    public function list_events(WP_REST_Request $request){
        $date = sanitize_text_field($request->get_param("date"));
        $query_args = array(
            "post_type" => $this->post_slug,
            "post_status" => "publish",
            "meta_query" => [],
        );

        if (!empty($date)) {
            $query_args["meta_query"][] = array(
                "key" => "start_datetime",
                "value" => $date,
                "compare" => "LIKE",
            );
        }

        $events = get_posts($query_args);
        $response = array();

        foreach ($events as $event) {
            $terms = get_the_terms($event->ID, $this->post_slug."_category");
            $category_names = array();

            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $category_names[] = $term->name;
                }
            }

            $response[] = array(
                "id" => $event->ID,
                "title" => $event->post_title,
                "description" => $event->post_content,
                "start_datetime" => get_post_meta(
                    $event->ID,
                    "start_datetime",
                    true
                ),
                "end_datetime" => get_post_meta(
                    $event->ID,
                    "end_datetime",
                    true
                ),
                "category" => implode(", ", $category_names),
			);
        }

        if (empty($response)) {
            return new WP_Error("not_found_post", "No events found", array(
                "status" => 404,
            ));
        }

        return rest_ensure_response($response);
    }

    // Show Event Callback
    public function show_event(WP_REST_Request $request){
        $id = intval($request->get_param("id"));
        $event = get_post($id);

        if (!$event || $event->post_type !== $this->post_slug) {
            return new WP_Error("not_found", "Event not found", array(
                "status" => 404,
            ));
        }

        $terms = get_the_terms($id, $this->post_slug."_category");
        $category_names = array();

        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $category_names[] = $term->name;
            }
        }
        $response = array(
            "id" => $event->ID,
            "title" => $event->post_title,
            "description" => $event->post_content,
            "start_datetime" => get_post_meta(
                $event->ID,
                "start_datetime",
                true
            ),
            "end_datetime" => get_post_meta($event->ID, "end_datetime", true),
            "category" => implode(", ", $category_names),
        );

        return rest_ensure_response($response);
    }

    // Delete Event Callback
    public function delete_event(WP_REST_Request $request){
        $id = intval($request->get_param("id"));

        if (!$id || get_post_type($id) !== $this->post_slug) {
            return new WP_Error("not_found", "Event not found", array(
                "status" => 404,
            ));
        }

        wp_delete_post($id, true);

        return rest_ensure_response(array(
            "message" => "Event deleted successfully",
        ));
    }

    // Token generation callback
    public function custom_auth_token(WP_REST_Request $request){
        $username = sanitize_text_field($request->get_param("username"));
        $password = sanitize_text_field($request->get_param("password"));

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return new WP_Error(
                "invalid_login",
                "Invalid username or password",
                array("status" => 403)
            );
        }
		
		$user_roles = $user->roles;
		$can_generate_token = false;
		foreach ($this->allowed_roles as $role) {
			if (in_array($role, $user_roles)) {
				$can_generate_token = true;
				break;
			}
		}
		if (!$can_generate_token) {
			return new WP_Error("not_allowed", "You do not have permission to generate a token", array("status" => 403));
		}

        $token = $this->generate_token($user->ID);

        return rest_ensure_response(array("token" => $token, "token_type" => "Bearer", "expires_in" => 3600));
    }

    // Token generation
    private function generate_token($user_id){
        $issued_at = time();
        $expiration = $issued_at + 3600; 
        $payload = json_encode(array(
            "user_id" => $user_id,
            "iat" => $issued_at,
            "exp" => $expiration,
        ));

        $signature = hash_hmac("sha256", $payload, $this->secret_key);

        return base64_encode($payload . "." . $signature);
    }

    // Token validation
    private function validate_token($token){
        $token_parts = explode(".", base64_decode($token));
        $payload = $token_parts[0];
        $provided_signature = $token_parts[1];

        $calculated_signature = hash_hmac(
            "sha256",
            $payload,
            $this->secret_key
        );

        if (!hash_equals($calculated_signature, $provided_signature)) {
            return false;
        }

        $data = json_decode($payload, true);

        if ($data["exp"] < time()) {
            return false;
        }

        return $data["user_id"];
    }

    // Category Assignment
    private function assign_category($category_names){
        $category_names = explode(",", $category_names);
        $term_ids = array();

        foreach ($category_names as $category_name) {
            $category_name = trim($category_name);
            $term = term_exists($category_name, $this->post_slug."_category");

            if (!$term) {
                $term = wp_insert_term($category_name, $this->post_slug."_category");
            }

            if (!is_wp_error($term)) {
                $term_ids[] = $term["term_id"];
            }
        }

        return $term_ids;
    }

    // Date format validation
    private function validate_date_format($date)
    {
        return DateTime::createFromFormat("Y-m-d H:i:s", $date) !== false;
    }
}
new Events_API_Plugin();
