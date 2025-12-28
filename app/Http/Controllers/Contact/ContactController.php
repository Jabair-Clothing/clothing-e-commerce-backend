<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contact;

class ContactController extends Controller
{
    //post contact form data
    public function store(Request $request)
    {
        $request->validate([
            'f_name' => 'required|string|max:255',
            'l_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'project_type' => 'required|string|max:20',
            'message' => 'required|string|max:500',
        ]);

        $contact = Contact::create($request->all());

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Contact form submitted successfully.',
            'data' => $contact,
        ], 201);
    }

    // Get all contacts
    public function index(Request $request)
    {
        try {
            $contacts = Contact::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Contacts fetched successfully.',
                'data' => $contacts,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while fetching contacts.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // delete contact
    public function destroy($id)
    {
        try {
            $contact = Contact::findOrFail($id);
            $contact->delete();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Contact deleted successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the contact.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
