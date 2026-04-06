<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;

use App\Events\MessageSent;
use App\Models\Group;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    // Show chat view for a group
    public function show(Group $group)
    {
        $this->authorize('view', $group);

        return view('groups.chat', ['group' => $group]);
    }

    // Fetch recent messages for a group
    public function fetchMessages(Request $request, Group $group)
    {
        $this->authorize('view', $group);

        $lastMessageId = $request->query('last_message_id');

        if ($lastMessageId) {
            // Fetch only new messages since last_message_id
            $messages = Message::with('user')
                ->forGroup($group->group_id)
                ->where('id', '>', $lastMessageId)
                ->orderBy('created_at', 'asc')
                ->get();
        } else {
            // Initial load - fetch recent messages
            $messages = Message::with('user')
                ->forGroup($group->group_id)
                ->recent(50)
                ->get()
                ->reverse()
                ->values();
        }

        return response()->json($messages);
    }

    // Send a message or file in the chat
    public function sendMessage(Request $request, Group $group)
    {
        try {
            $this->authorize('view', $group);

            $validator = Validator::make($request->all(), [
                'message' => 'nullable|string|max:1000',
                'file' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,gif,mp4,avi,zip,rar,mp3,wav',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors()->all();

                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed: '.implode(', ', $errors),
                ], 422);
            }

            $messageText = trim($request->input('message', ''));
            $filePath = null;
            $fileName = null;
            $fileSize = null;

            if ($request->hasFile('file')) {
                $file = $request->file('file');

                if (! $file->isValid()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid file uploaded',
                    ], 422);
                }

                $fileName = $file->getClientOriginalName();
                $fileSize = $file->getSize();
                $maxSize = 10 * 1024 * 1024; // 10MB

                if ($fileSize > $maxSize) {
                    return response()->json([
                        'success' => false,
                        'error' => 'File size exceeds 10MB limit',
                    ], 422);
                }

                $dangerousExtensions = ['php', 'exe', 'bat', 'cmd', 'scr', 'pif', 'com'];
                $extension = strtolower($file->getClientOriginalExtension());
                if (in_array($extension, $dangerousExtensions)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'File type not allowed for security reasons',
                    ], 422);
                }

                try {
                    $fileContents = file_get_contents($file->getRealPath());
                    if ($fileContents === false) {
                        throw new \Exception('Failed to read file contents');
                    }

                    $encryptedContents = Crypt::encryptString(base64_encode($fileContents));
                    $filePath = 'chat_files/'.uniqid().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);

                    if (! Storage::put($filePath, $encryptedContents)) {
                        throw new \Exception('Failed to save file');
                    }
                } catch (\Exception $e) {
                    \Log::error('File upload error: '.$e->getMessage());

                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to process file. Please try again.',
                    ], 500);
                }
            }

            if (empty($messageText) && ! $filePath) {
                return response()->json([
                    'success' => false,
                    'error' => 'Please enter a message or upload a file',
                ], 422);
            }

            $message = Message::create([
                'group_id' => $group->group_id,
                'user_id' => Auth::id(),
                'message' => $messageText,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
            ]);

            try {
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Exception $e) {
                \Log::warning('Broadcasting failed: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to send messages in this group',
            ], 403);
        } catch (\Exception $e) {
            \Log::error('Chat sendMessage error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again.',
            ], 500);
        }
    }

    // Download encrypted file
    public function downloadFile(Group $group, Message $message)
    {
        try {
            $this->authorize('view', $group);

            // Ensure the message belongs to the group
            if ($message->group_id !== $group->group_id) {
                \Log::warning("Unauthorized file download attempt: Message {$message->id} does not belong to group {$group->group_id}");
                abort(404, 'File not found');
            }

            // Ensure the message has a file
            if (! $message->file_path) {
                abort(404, 'File not found');
            }

            // Check if file exists in storage
            if (! Storage::exists($message->file_path)) {
                \Log::error("File not found in storage: {$message->file_path}");
                abort(404, 'File not found');
            }

            try {
                // Get the encrypted file content
                $encryptedContent = Storage::get($message->file_path);

                if (empty($encryptedContent)) {
                    \Log::error("Empty file content for: {$message->file_path}");
                    abort(404, 'File is corrupted');
                }

                // Decrypt the file content
                $decryptedContent = base64_decode(Crypt::decryptString($encryptedContent));

                if ($decryptedContent === false) {
                    \Log::error("Failed to decrypt file: {$message->file_path}");
                    abort(404, 'File is corrupted');
                }

                // Determine MIME type based on file extension
                $extension = strtolower(pathinfo($message->file_name, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls' => 'application/vnd.ms-excel',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'ppt' => 'application/vnd.ms-powerpoint',
                    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'txt' => 'text/plain',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'mp4' => 'video/mp4',
                    'avi' => 'video/x-msvideo',
                    'mp3' => 'audio/mpeg',
                    'wav' => 'audio/wav',
                    'zip' => 'application/zip',
                    'rar' => 'application/x-rar-compressed',
                ];

                $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

                // Return the file as a download response
                return response($decryptedContent)
                    ->header('Content-Type', $contentType)
                    ->header('Content-Disposition', 'attachment; filename="'.$message->file_name.'"')
                    ->header('Content-Length', strlen($decryptedContent))
                    ->header('Cache-Control', 'private, max-age=0');

            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                \Log::error("Decryption failed for file: {$message->file_path} - ".$e->getMessage());
                abort(404, 'File is corrupted');
            } catch (\Exception $e) {
                \Log::error("File processing error for {$message->file_path}: ".$e->getMessage());
                abort(404, 'File processing error');
            }

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            abort(403, 'Access denied');
        } catch (\Exception $e) {
            \Log::error('Download file error: '.$e->getMessage());
            abort(500, 'Internal server error');
        }
    }
}
