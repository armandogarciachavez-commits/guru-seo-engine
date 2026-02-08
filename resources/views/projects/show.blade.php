@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <a href="{{ route('projects.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; Back to Projects</a>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Project: {{ $project->name }}
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                {{ $project->domain_url }} ({{ $project->target_language }})
            </p>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                UUID: <code class="bg-gray-100 p-1">{{ $project->uuid }}</code>
            </p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200">
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Widget Script</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <code
                            class="block whitespace-pre-wrap bg-gray-800 text-white p-3 rounded text-xs">&lt;div id="guru-feed"&gt;&lt;/div&gt;
        &lt;script src="{{ asset('js/guru-loader.js') }}" data-project-uuid="{{ $project->uuid }}" data-container-id="guru-feed"&gt;&lt;/script&gt;</code>
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg mb-6 p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Generate New Article</h3>
        <form action="{{ route('projects.articles.store', $project) }}" method="POST" class="flex gap-4">
            @csrf
            <div class="flex-grow">
                <input type="text" name="keyword" placeholder="Enter target keyword (e.g. 'best seo tools')"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    required>
            </div>
            <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded flex-shrink-0">
                Generate Content
            </button>
        </form>
    </div>

    <h3 class="text-lg font-medium text-gray-900 mb-4">Generated Articles</h3>
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <ul role="list" class="divide-y divide-gray-200">
            @forelse($project->articles()->latest()->get() as $article)
                <li>
                    <div class="px-4 py-4 sm:px-6">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-indigo-600 truncate">{{ $article->keyword }}</p>
                            <div class="ml-2 flex-shrink-0 flex items-center space-x-2">
                                <a href="{{ route('articles.show', $article->slug) }}" target="_blank"
                                    class="text-xs text-indigo-600 hover:text-indigo-900 border border-indigo-600 px-2 py-1 rounded">
                                    View Full Article
                                </a>
                                <p
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $article->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ $article->status }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-2">
                            <details>
                                <summary class="cursor-pointer text-sm text-gray-500">View Content Preview</summary>
                                <div class="mt-2 p-3 bg-gray-50 border rounded text-xs text-gray-700">
                                    {{ Str::limit(strip_tags($article->html_content), 300) }}
                                </div>
                            </details>
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-4 py-4 sm:px-6 text-gray-500 text-center">No articles generated yet.</li>
            @endforelse
        </ul>
    </div>
@endsection