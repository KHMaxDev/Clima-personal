import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';

export default function ActivityCalendar({
    events = [],
    fetchUrl = null,
    initialView = 'dayGridMonth',
}) {
    const source = fetchUrl
        ? (info, successCallback, failureCallback) => {
              const url = new URL(fetchUrl, window.location.origin);
              url.searchParams.set('start', info.startStr);
              url.searchParams.set('end', info.endStr);

              fetch(url, {
                  credentials: 'same-origin',
                  headers: { Accept: 'application/json' },
              })
                  .then((response) => response.json())
                  .then((data) => successCallback(data.events ?? data))
                  .catch(failureCallback);
          }
        : events;

    return (
        <div className="glass-card fc-theme-app p-3">
            <FullCalendar
                plugins={[
                    dayGridPlugin,
                    timeGridPlugin,
                    listPlugin,
                    interactionPlugin,
                ]}
                initialView={initialView}
                locale={esLocale}
                firstDay={1}
                height="auto"
                nowIndicator
                dayMaxEvents={3}
                headerToolbar={{
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek',
                }}
                buttonText={{
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    list: 'Lista',
                }}
                events={source}
                eventClick={(info) => {
                    if (info.event.url) {
                        info.jsEvent.preventDefault();
                        window.location.href = info.event.url;
                    }
                }}
                eventTimeFormat={{
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }}
            />
        </div>
    );
}
