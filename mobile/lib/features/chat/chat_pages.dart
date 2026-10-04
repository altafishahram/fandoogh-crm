import 'dart:async';
import 'dart:io';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/storage/android_media_picker.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/features/chat/chat_repository.dart';
import 'package:fandoogh_crm/features/marketplace/presentation/marketplace_pages.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';

final chatRoutes = <RouteBase>[
  GoRoute(path: '/conversations', builder: (_, _) => const ConversationsPage()),
  GoRoute(
    path: '/conversations/new',
    builder: (_, state) => StartConversationPage(
      listing: int.parse(state.uri.queryParameters['listing']!),
      audience: state.uri.queryParameters['audience'] ?? 'public',
    ),
  ),
  GoRoute(
    path: '/conversations/:id',
    builder: (_, state) =>
        ConversationPage(id: int.parse(state.pathParameters['id']!)),
  ),
];

final class ConversationsPage extends ConsumerStatefulWidget {
  const ConversationsPage({super.key});
  @override
  ConsumerState<ConversationsPage> createState() => _ConversationsPageState();
}

final class _ConversationsPageState extends ConsumerState<ConversationsPage> {
  List<Conversation> _items = [];
  Object? _error;
  bool _busy = false, _hasMore = false;
  int _page = 1;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool more = false}) async {
    setState(() => _busy = true);
    try {
      final result = await ref
          .read(chatRepositoryProvider)
          .conversations(page: more ? _page + 1 : 1);
      if (mounted) {
        setState(() {
          _items = more ? [..._items, ...result.items] : result.items;
          _page = result.currentPage;
          _hasMore = result.hasMore;
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('گفت‌وگوها')),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          if (_error != null) ErrorState(error: _error!, onRetry: _load),
          if (!_busy && _items.isEmpty && _error == null)
            const Padding(
              padding: EdgeInsets.all(32),
              child: Text(
                'هنوز گفت‌وگویی ندارید.',
                textAlign: TextAlign.center,
              ),
            ),
          ..._items.map(
            (c) => ListTile(
              title: Text(c.title),
              subtitle: Text(
                c.canSend
                    ? 'گفت‌وگو درباره آگهی'
                    : 'آگهی بسته شده؛ فقط تاریخچه',
              ),
              leading: Badge(
                isLabelVisible: c.unreadCount > 0,
                label: Text('${c.unreadCount}'),
                child: const Icon(Icons.chat_bubble_outline),
              ),
              onTap: () async {
                await context.push('/conversations/${c.id}');
                if (mounted) _load();
              },
            ),
          ),
          if (_busy) const Center(child: CircularProgressIndicator()),
          if (_hasMore && !_busy)
            TextButton(
              onPressed: () => _load(more: true),
              child: const Text('نمایش بیشتر'),
            ),
        ],
      ),
    ),
  );
}

final class StartConversationPage extends ConsumerStatefulWidget {
  const StartConversationPage({
    required this.listing,
    required this.audience,
    super.key,
  });
  final int listing;
  final String audience;
  @override
  ConsumerState<StartConversationPage> createState() =>
      _StartConversationPageState();
}

final class _StartConversationPageState
    extends ConsumerState<StartConversationPage> {
  Object? _error;
  @override
  void initState() {
    super.initState();
    _start();
  }

  Future<void> _start() async {
    setState(() => _error = null);
    try {
      final c = await ref
          .read(chatRepositoryProvider)
          .start(widget.listing, widget.audience);
      if (mounted) context.replace('/conversations/${c.id}');
    } catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('شروع گفت‌وگو')),
    body: _error == null
        ? const Center(child: CircularProgressIndicator())
        : ErrorState(error: _error!, onRetry: _start),
  );
}

final class ConversationPage extends ConsumerStatefulWidget {
  const ConversationPage({required this.id, super.key});
  final int id;
  @override
  ConsumerState<ConversationPage> createState() => _ConversationPageState();
}

final class _ConversationPageState extends ConsumerState<ConversationPage>
    with WidgetsBindingObserver {
  final _body = TextEditingController();
  final _scroll = ScrollController();
  Timer? _timer;
  List<ChatMessage> _messages = [];
  bool _busy = true, _sending = false, _refreshing = false, _canSend = false;
  Object? _error;
  String? _image, _requestId;
  int? _nextPage;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
    _timer = Timer.periodic(const Duration(seconds: 15), (_) => _load());
  }

  @override
  void dispose() {
    _timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    _body.dispose();
    _scroll.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _timer ??= Timer.periodic(const Duration(seconds: 15), (_) => _load());
      _load();
    } else {
      _timer?.cancel();
      _timer = null;
    }
  }

  Future<Conversation?> _conversation() async {
    int page = 1;
    while (true) {
      final result = await ref
          .read(chatRepositoryProvider)
          .conversations(page: page);
      for (final c in result.items) {
        if (c.id == widget.id) return c;
      }
      if (!result.hasMore) return null;
      page++;
    }
  }

  Future<void> _load({bool more = false}) async {
    if (_refreshing) return;
    _refreshing = true;
    try {
      final repository = ref.read(chatRepositoryProvider);
      final conversation = await _conversation();
      final result = await repository.messages(
        widget.id,
        page: more ? (_nextPage ?? 1) : 1,
        afterId: !more && _messages.isNotEmpty ? _messages.last.id : null,
      );
      if (!mounted) return;
      final map = {
        for (final m in _messages) m.id: m,
        for (final m in result.items) m.id: m,
      };
      setState(() {
        _messages = map.values.toList()..sort((a, b) => a.id.compareTo(b.id));
        _canSend = conversation?.canSend ?? false;
        _busy = false;
        _error = null;
        if (more || _nextPage == null) {
          _nextPage = result.hasMore ? result.currentPage + 1 : null;
        }
      });
      if (_messages.isNotEmpty) {
        await repository.read(widget.id, _messages.last.id);
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e;
          _busy = false;
          _canSend = false;
          final status = apiFailureFrom(e).statusCode;
          if (status == 401 || status == 403 || status == 404) _messages = [];
        });
      }
    } finally {
      _refreshing = false;
    }
  }

  Future<void> _pick() async {
    try {
      final path = await AndroidMediaPicker.pickImage();
      if (path == null) return;
      if (await File(path).length() > 10 * 1024 * 1024) {
        throw const FormatException('حداکثر حجم عکس ۱۰ مگابایت است.');
      }
      if (mounted) {
        setState(() {
          _image = path;
          _requestId = null;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              e is FormatException ? e.message : 'انتخاب تصویر انجام نشد.',
            ),
          ),
        );
      }
    }
  }

  Future<void> _send() async {
    if (_body.text.trim().isEmpty && _image == null) return;
    setState(() {
      _sending = true;
      _error = null;
    });
    _requestId ??= const Uuid().v4();
    try {
      final message = await ref
          .read(chatRepositoryProvider)
          .send(
            widget.id,
            requestId: _requestId!,
            body: _body.text,
            imagePath: _image,
          );
      if (!mounted) return;
      setState(() {
        _messages = [..._messages.where((m) => m.id != message.id), message]
          ..sort((a, b) => a.id.compareTo(b.id));
        _body.clear();
        _image = null;
        _requestId = null;
      });
      await _load();
    } catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('گفت‌وگو درباره آگهی')),
    body: Column(
      children: [
        if (!_canSend && !_busy)
          const Padding(
            padding: EdgeInsets.all(12),
            child: Text('ارسال پیام برای این گفت‌وگو در دسترس نیست.'),
          ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.all(8),
            child: TextButton(
              onPressed: () => _load(),
              child: Text('${apiFailureFrom(_error!).message} — تلاش دوباره'),
            ),
          ),
        Expanded(
          child: _busy
              ? const Center(child: CircularProgressIndicator())
              : ListView(
                  controller: _scroll,
                  padding: const EdgeInsets.all(12),
                  children: [
                    if (_nextPage != null)
                      TextButton(
                        onPressed: () => _load(more: true),
                        child: const Text('نمایش پیام‌های بیشتر'),
                      ),
                    ..._messages.map(
                      (m) => Align(
                        alignment: m.isMine
                            ? Alignment.centerRight
                            : Alignment.centerLeft,
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 360),
                          child: Card(
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (m.imageUrl != null && _canSend)
                                    AuthenticatedPhoto(
                                      m.imageUrl!,
                                      height: 180,
                                    ),
                                  if (m.body.isNotEmpty) Text(m.body),
                                  Text(
                                    m.isMine ? 'شما' : 'طرف گفت‌وگو',
                                    style: Theme.of(
                                      context,
                                    ).textTheme.labelSmall,
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                if (_image != null)
                  ListTile(
                    title: const Text('یک عکس آماده ارسال'),
                    trailing: IconButton(
                      tooltip: 'حذف عکس انتخاب‌شده',
                      onPressed: _sending
                          ? null
                          : () => setState(() => _image = null),
                      icon: const Icon(Icons.close),
                    ),
                  ),
                Row(
                  children: [
                    IconButton(
                      tooltip: 'ارسال عکس',
                      onPressed: _canSend && !_sending ? _pick : null,
                      icon: const Icon(Icons.photo_outlined),
                    ),
                    Expanded(
                      child: TextField(
                        controller: _body,
                        enabled: _canSend && !_sending,
                        minLines: 1,
                        maxLines: 4,
                        onChanged: (_) => _requestId = null,
                        decoration: const InputDecoration(labelText: 'پیام'),
                      ),
                    ),
                    IconButton(
                      tooltip: 'ارسال پیام',
                      onPressed: _canSend && !_sending ? _send : null,
                      icon: _sending
                          ? const CircularProgressIndicator()
                          : const Icon(Icons.send),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}
