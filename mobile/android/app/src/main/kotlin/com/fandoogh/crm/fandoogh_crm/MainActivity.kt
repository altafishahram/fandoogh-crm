package com.fandoogh.crm.fandoogh_crm

import android.app.Activity
import android.content.Intent
import android.net.Uri
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File

class MainActivity : FlutterActivity() {
    private val imagePickerRequest = 4401
    private var pendingImageResult: MethodChannel.Result? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.fandoogh.crm/media")
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "pickImage" -> {
                        if (pendingImageResult != null) {
                            result.error("PICKER_BUSY", "The image picker is already open.", null)
                        } else {
                            pendingImageResult = result
                            val intent = Intent(Intent.ACTION_OPEN_DOCUMENT).apply {
                                addCategory(Intent.CATEGORY_OPENABLE)
                                type = "image/*"
                            }
                            startActivityForResult(intent, imagePickerRequest)
                        }
                    }
                    else -> result.notImplemented()
                }
            }
    }

    @Deprecated("Deprecated by Android, retained for FlutterActivity compatibility.")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode != imagePickerRequest) return
        val result = pendingImageResult ?: return
        pendingImageResult = null
        val uri: Uri? = if (resultCode == Activity.RESULT_OK) data?.data else null
        if (uri == null) {
            result.success(null)
            return
        }
        try {
            val mimeType = contentResolver.getType(uri)
            val extension = when (mimeType) {
                "image/png" -> ".png"
                "image/webp" -> ".webp"
                else -> ".jpg"
            }
            val target = File.createTempFile("fandoogh-upload-", extension, cacheDir)
            contentResolver.openInputStream(uri).use { input ->
                requireNotNull(input) { "Selected image cannot be opened." }
                target.outputStream().use { output -> input.copyTo(output) }
            }
            result.success(target.absolutePath)
        } catch (error: Exception) {
            result.error("IMAGE_READ_FAILED", "Selected image could not be prepared.", null)
        }
    }
}
