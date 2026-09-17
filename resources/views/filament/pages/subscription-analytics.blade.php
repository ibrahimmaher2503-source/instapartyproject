<x-filament-panels::page>
    <x-filament-widgets::widgets
        :columns="$this->getHeaderWidgetsColumns()"
        :widgets="$this->getHeaderWidgets()"
        :data="$this->getWidgetData()"
    />
</x-filament-panels::page>
