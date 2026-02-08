<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index()
    {
        // For MVP without full Auth scaffolding, fallback to '1' or create a dummy user logic if needed.
        // But standard Laravel Auth is expected.
        $userId = Auth::id() ?? 1;

        // If no user exists yet, we might need to create one, but let's assume one exists or we just use ID 1 for now.
        // Actually best is to check if user exists, if not create. But that's seed logic.

        $projects = Project::where('user_id', $userId)->latest()->get();
        return view('projects.index', compact('projects'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain_url' => 'required|url',
            'cms_type' => 'required|in:wordpress,shopify,custom_html',
            'target_language' => 'required|string',
            'brand_voice' => 'nullable|string',
            'target_city' => 'nullable|string',
        ]);

        $project = new Project($validated);
        $project->user_id = Auth::id() ?? 1; // Fallback for dev
        $project->save();

        return redirect()->route('projects.index')->with('success', 'Project created successfully.');
    }

    public function show(Project $project)
    {
        // Ensure user owns project
        if ($project->user_id !== (Auth::id() ?? 1)) {
            abort(403);
        }

        return view('projects.show', compact('project'));
    }
}
