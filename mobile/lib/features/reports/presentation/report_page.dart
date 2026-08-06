import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/core/widgets/section_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class ReportPage extends ConsumerStatefulWidget {
  const ReportPage({super.key});
  @override
  ConsumerState<ReportPage> createState() => _ReportPageState();
}

final class _ReportPageState extends ConsumerState<ReportPage> {
  late DateTime _from;
  late DateTime _to;
  AsyncValue<Map<String, dynamic>>? _report;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _to = DateTime(now.year, now.month, now.day);
    _from = _to.subtract(const Duration(days: 30));
    Future<void>.microtask(_load);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('گزارش من')),
    body: Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: <Widget>[
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickDate(true),
                  icon: const Icon(Icons.date_range),
                  label: Text('از ${_format(_from)}'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickDate(false),
                  icon: const Icon(Icons.event),
                  label: Text('تا ${_format(_to)}'),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filled(
                tooltip: 'اجرای گزارش',
                onPressed: _load,
                icon: const Icon(Icons.refresh_rounded),
              ),
            ],
          ),
        ),
        Expanded(child: _body()),
      ],
    ),
  );

  Widget _body() {
    final report = _report;
    if (report == null || report.isLoading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (report.hasError) {
      return ErrorState(error: report.error!, onRetry: _load);
    }
    final data = report.requireValue;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 100),
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: _MetricCard(
                  label: 'املاک واگذارشده',
                  value: '${data['assigned_properties'] ?? 0}',
                  icon: Icons.apartment_outlined,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _MetricCard(
                  label: 'مشتریان ایجادشده',
                  value: '${data['customers_created'] ?? 0}',
                  icon: Icons.person_add_alt,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _MapSection(
            title: 'تغییر وضعیت املاک در بازه',
            value: data['property_status_changes'],
            labels: propertyStatuses,
          ),
          const SizedBox(height: 12),
          _MapSection(
            title: 'مشتریان بر اساس وضعیت',
            value: data['customers_by_status'],
            labels: customerStatuses,
          ),
        ],
      ),
    );
  }

  Future<void> _pickDate(bool start) async {
    final value = await showDatePicker(
      context: context,
      initialDate: start ? _from : _to,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 1)),
    );
    if (value == null) return;
    setState(() {
      if (start) {
        _from = value;
      } else {
        _to = value;
      }
    });
  }

  Future<void> _load() async {
    if (_from.isAfter(_to)) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('تاریخ شروع باید پیش از پایان باشد.')),
        );
      }
      return;
    }
    setState(() => _report = const AsyncLoading<Map<String, dynamic>>());
    final value = await AsyncValue.guard(
      () => ApiRepository(
        ref.read(apiClientProvider),
      ).getOne('/reports/me?from=${_format(_from)}&to=${_format(_to)}'),
    );
    if (mounted) setState(() => _report = value);
  }

  static String _format(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
}

final class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.icon,
  });
  final String label;
  final String value;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: <Widget>[
          Icon(icon, size: 34, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 8),
          Text(value, style: Theme.of(context).textTheme.headlineSmall),
          Text(label, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

final class _MapSection extends StatelessWidget {
  const _MapSection({
    required this.title,
    required this.value,
    required this.labels,
  });
  final String title;
  final Object? value;
  final List<ChoiceItem> labels;
  @override
  Widget build(BuildContext context) {
    final map = value is Map
        ? Map<String, dynamic>.from(value! as Map)
        : <String, dynamic>{};
    return SectionCard(
      title: title,
      child: map.isEmpty
          ? const Text('داده‌ای در این بازه وجود ندارد.')
          : Column(
              children: map.entries
                  .map(
                    (entry) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(labelOf(labels, entry.key)),
                      trailing: CircleAvatar(child: Text('${entry.value}')),
                    ),
                  )
                  .toList(growable: false),
            ),
    );
  }
}
